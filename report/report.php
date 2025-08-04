<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Basic authentication check
if (!isset($_SESSION['admin_id'])) {
    header('Location: /login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch admin info for navbar
$admin_info = ['name' => 'Guest'];
if (isset($_SESSION['admin_id'])) {
    $stmt = $db->prepare("SELECT name FROM admin WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['admin_id']);
    $stmt->execute();
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

$report_data = [];
$message = '';
$message_type = '';

// Get report parameters
$report_type = $_GET['type'] ?? 'monthly';
$custom_start = $_GET['start_date'] ?? '';
$custom_end = $_GET['end_date'] ?? '';

// Calculate date ranges based on report type
function getDateRange($type, $custom_start = '', $custom_end = '') {
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d');
    
    switch ($type) {
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'monthly':
            $start_date = date('Y-m-01');
            break;
        case 'yearly':
            $start_date = date('Y-01-01');
            break;
        case 'all_time':
            $start_date = '2020-01-01';
            break;
        case 'custom':
            if ($custom_start && $custom_end) {
                $start_date = $custom_start;
                $end_date = $custom_end;
            }
            break;
    }
    
    return ['start' => $start_date, 'end' => $end_date];
}

$date_range = getDateRange($report_type, $custom_start, $custom_end);
$period_label = '';

switch ($report_type) {
    case 'weekly':
        $period_label = 'Last 7 Days';
        break;
    case 'monthly':
        $period_label = date('F Y');
        break;
    case 'yearly':
        $period_label = date('Y');
        break;
    case 'all_time':
        $period_label = 'All Time';
        break;
    case 'custom':
        $period_label = date('M j, Y', strtotime($date_range['start'])) . ' - ' . date('M j, Y', strtotime($date_range['end']));
        break;
}

try {
    // --- 1. Fetch Top Customers ---
    $stmt = $db->prepare("
        SELECT c.name, c.customer_id, SUM(o.grand_total) as total_spent, COUNT(o.id) as order_count
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.status = 'paid'
        AND DATE(o.order_date) BETWEEN :start_date AND :end_date
        GROUP BY c.customer_id, c.name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->bindParam(':start_date', $date_range['start']);
    $stmt->bindParam(':end_date', $date_range['end']);
    $stmt->execute();
    $report_data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. Fetch Most Sold Items ---
    $stmt = $db->prepare("
        SELECT ii.name as item_name, ii.item_id, SUM(oi.quantity) as total_quantity_sold, 
               SUM(oi.subtotal) as total_revenue, AVG(oi.unit_price) as avg_price
        FROM order_items oi
        JOIN inventory_items ii ON oi.item_id = ii.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status = 'paid'
        AND DATE(o.order_date) BETWEEN :start_date AND :end_date
        GROUP BY ii.item_id, ii.name
        ORDER BY total_quantity_sold DESC
        LIMIT 10
    ");
    $stmt->bindParam(':start_date', $date_range['start']);
    $stmt->bindParam(':end_date', $date_range['end']);
    $stmt->execute();
    $report_data['most_sold_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Fetch Low Stock Items ---
    $stmt = $db->query("
        SELECT name, item_id, current_stock, minimum_stock, 
               (minimum_stock - current_stock) as shortage
        FROM inventory_items
        WHERE current_stock <= minimum_stock AND status = 'active'
        ORDER BY shortage DESC, current_stock ASC
    ");
    $report_data['low_stock_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. Financial Summary ---
    $stmt = $db->prepare("
        SELECT 
            SUM(grand_total) as total_revenue,
            COUNT(*) as total_orders,
            AVG(grand_total) as avg_order_value
        FROM orders
        WHERE status = 'paid'
        AND DATE(order_date) BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':start_date', $date_range['start']);
    $stmt->bindParam(':end_date', $date_range['end']);
    $stmt->execute();
    $financial_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 5. Calculate Expenses (COGS) ---
    $stmt = $db->prepare("
        SELECT SUM(oi.quantity * ii.unit_cost) as total_expenses
        FROM order_items oi
        JOIN inventory_items ii ON oi.item_id = ii.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status = 'paid'
        AND DATE(o.order_date) BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':start_date', $date_range['start']);
    $stmt->bindParam(':end_date', $date_range['end']);
    $stmt->execute();
    $expenses = $stmt->fetchColumn() ?: 0.00;

    $report_data['financial'] = [
        'total_revenue' => $financial_summary['total_revenue'] ?: 0.00,
        'total_expenses' => $expenses,
        'net_profit' => ($financial_summary['total_revenue'] ?: 0.00) - $expenses,
        'total_orders' => $financial_summary['total_orders'] ?: 0,
        'avg_order_value' => $financial_summary['avg_order_value'] ?: 0.00
    ];

    // --- 6. Payment Method Analysis ---
    $stmt = $db->prepare("
        SELECT payment_method, COUNT(*) as count, SUM(grand_total) as total_amount
        FROM orders
        WHERE status = 'paid' AND payment_method IS NOT NULL
        AND DATE(order_date) BETWEEN :start_date AND :end_date
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->bindParam(':start_date', $date_range['start']);
    $stmt->bindParam(':end_date', $date_range['end']);
    $stmt->execute();
    $report_data['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error generating report: " . $e->getMessage());
    $message = 'Error generating report: ' . $e->getMessage();
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            font-weight: 300;
        }
        main {
            margin-left: 16rem;
            margin-top: 4rem;
            padding: 1.5rem;
        }
        @media (max-width: 768px) {
            main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-sm">
    <?php include __DIR__ . '/../component/navbar.php'; ?>
    
    <div class="flex">
        <?php include __DIR__ . '/../component/sidebar.php'; ?>
        
        <main class="flex-1">
            <!-- Header Section -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-light text-gray-800 mb-1">Reports & Analytics</h1>
                        <p class="text-gray-500 text-xs">Business insights for <?php echo htmlspecialchars($period_label); ?></p>
                    </div>
                    <div class="mt-3 md:mt-0 space-x-2">
                        <button onclick="window.print()" class="bg-primary hover:bg-primary-dark text-white px-3 py-1.5 rounded text-xs font-light">
                            Print Report
                        </button>
                        <button onclick="exportReport()" class="bg-accent hover:bg-accent-dark text-white px-3 py-1.5 rounded text-xs font-light">
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Range Selector -->
            <div class="bg-white rounded shadow-sm p-4 mb-6">
                <h3 class="text-sm font-normal text-gray-700 mb-3">Report Period</h3>
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Report Type</label>
                        <select name="type" id="reportType" onchange="toggleCustomDates()" 
                                class="px-2 py-1.5 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-primary focus:border-transparent">
                            <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>This Month</option>
                            <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>This Year</option>
                            <option value="all_time" <?php echo $report_type === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                            <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="customDates" class="flex gap-3" style="display: <?php echo $report_type === 'custom' ? 'flex' : 'none'; ?>;">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($custom_start); ?>"
                                   class="px-2 py-1.5 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($custom_end); ?>"
                                   class="px-2 py-1.5 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-1.5 rounded text-xs font-light">
                        Generate Report
                    </button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="p-3 mb-6 text-xs rounded <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
                <!-- Financial Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded shadow-sm p-4">
                        <p class="text-xs text-gray-500 mb-1">Total Revenue</p>
                        <p class="text-lg font-light text-green-600">Rs<?php echo number_format($report_data['financial']['total_revenue'], 2); ?></p>
                    </div>

                    <div class="bg-white rounded shadow-sm p-4">
                        <p class="text-xs text-gray-500 mb-1">Total Expenses</p>
                        <p class="text-lg font-light text-red-600">Rs<?php echo number_format($report_data['financial']['total_expenses'], 2); ?></p>
                    </div>

                    <div class="bg-white rounded shadow-sm p-4">
                        <p class="text-xs text-gray-500 mb-1">Net Profit</p>
                        <p class="text-lg font-light <?php echo $report_data['financial']['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            Rs<?php echo number_format($report_data['financial']['net_profit'], 2); ?>
                        </p>
                    </div>

                    <div class="bg-white rounded shadow-sm p-4">
                        <p class="text-xs text-gray-500 mb-1">Total Orders</p>
                        <p class="text-lg font-light text-primary"><?php echo number_format($report_data['financial']['total_orders']); ?></p>
                        <p class="text-xs text-gray-400">Avg: Rs<?php echo number_format($report_data['financial']['avg_order_value'], 2); ?></p>
                    </div>
                </div>

                <!-- Payment Methods Summary -->
                <?php if (!empty($report_data['payment_methods'])): ?>
                    <div class="bg-white rounded shadow-sm p-4 mb-6">
                        <h3 class="text-sm font-normal text-gray-700 mb-3">Payment Methods</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach ($report_data['payment_methods'] as $method): ?>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($method['payment_method']); ?></p>
                                    <p class="text-sm font-light text-gray-800">Rs<?php echo number_format($method['total_amount'], 2); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $method['count']; ?> orders</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Top Customers -->
                <div class="bg-white rounded shadow-sm p-4 mb-6">
                    <h3 class="text-sm font-normal text-gray-700 mb-3">Top Customers</h3>
                    <?php if (!empty($report_data['top_customers'])): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Rank</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Customer</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Customer ID</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Orders</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Total Spent</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Avg Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['top_customers'] as $index => $customer): ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-25">
                                            <td class="px-3 py-2 text-xs text-gray-600"><?php echo $index + 1; ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-800"><?php echo htmlspecialchars($customer['name']); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-500"><?php echo htmlspecialchars($customer['customer_id']); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-600"><?php echo number_format($customer['order_count']); ?></td>
                                            <td class="px-3 py-2 text-xs font-light text-green-600">Rs<?php echo number_format($customer['total_spent'], 2); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-500">Rs<?php echo number_format($customer['total_spent'] / $customer['order_count'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <p class="text-xs text-gray-400">No customer data found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Most Sold Items -->
                <div class="bg-white rounded shadow-sm p-4 mb-6">
                    <h3 class="text-sm font-normal text-gray-700 mb-3">Best Selling Items</h3>
                    <?php if (!empty($report_data['most_sold_items'])): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Rank</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Item</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Item ID</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Quantity Sold</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Revenue</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Avg Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['most_sold_items'] as $index => $item): ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-25">
                                            <td class="px-3 py-2 text-xs text-gray-600"><?php echo $index + 1; ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-800"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-500"><?php echo htmlspecialchars($item['item_id']); ?></td>
                                            <td class="px-3 py-2 text-xs text-primary"><?php echo number_format($item['total_quantity_sold']); ?></td>
                                            <td class="px-3 py-2 text-xs font-light text-green-600">Rs<?php echo number_format($item['total_revenue'], 2); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-500">Rs<?php echo number_format($item['avg_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <p class="text-xs text-gray-400">No sales data found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Low Stock Alert -->
                <?php if (!empty($report_data['low_stock_items'])): ?>
                    <div class="bg-white rounded shadow-sm p-4 mb-6">
                        <h3 class="text-sm font-normal text-gray-700 mb-3">Low Stock Alert</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Item</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Current Stock</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Minimum Stock</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Shortage</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-normal">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['low_stock_items'] as $item): ?>
                                        <tr class="border-b border-gray-50 hover:bg-red-25">
                                            <td class="px-3 py-2 text-xs text-gray-800"><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td class="px-3 py-2 text-xs text-red-600"><?php echo number_format($item['current_stock']); ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-500"><?php echo number_format($item['minimum_stock']); ?></td>
                                            <td class="px-3 py-2 text-xs text-red-600"><?php echo number_format($item['shortage']); ?></td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">
                                                    <?php echo $item['current_stock'] == 0 ? 'Out of Stock' : 'Low Stock'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#0A3167',
                            dark: '#082850'
                        },
                        accent: {
                            DEFAULT: '#C5A572',
                            dark: '#B89355'
                        }
                    },
                    fontFamily: {
                        heading: ['Playfair Display', 'serif'],
                        body: ['Jalla One', 'sans-serif'],
                    }
                }
            }
        }

        function toggleCustomDates() {
            const reportType = document.getElementById('reportType').value;
            const customDates = document.getElementById('customDates');
            customDates.style.display = reportType === 'custom' ? 'flex' : 'none';
        }

        function exportReport() {
            const reportData = {
                period: '<?php echo addslashes($period_label); ?>',
                revenue: <?php echo $report_data['financial']['total_revenue'] ?? 0; ?>,
                expenses: <?php echo $report_data['financial']['total_expenses'] ?? 0; ?>,
                profit: <?php echo $report_data['financial']['net_profit'] ?? 0; ?>,
                orders: <?php echo $report_data['financial']['total_orders'] ?? 0; ?>
            };
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Report Period,Total Revenue (Rs),Total Expenses (Rs),Net Profit (Rs),Total Orders\n";
            csvContent += `"${reportData.period}",${reportData.revenue},${reportData.expenses},${reportData.profit},${reportData.orders}\n`;
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `billing_report_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>