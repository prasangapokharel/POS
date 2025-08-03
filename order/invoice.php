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

$order = null;
$customer = null;
$order_items = [];
$message = '';

if (isset($_GET['id'])) {
    $order_id = $_GET['id'];
    try {
        // Fetch order details
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id LIMIT 1");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Fetch customer details
            $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = :customer_id LIMIT 1");
            $stmt->bindParam(':customer_id', $order['customer_id']);
            $stmt->execute();
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch order items with item names
            $stmt = $db->prepare("SELECT oi.*, ii.name as item_name FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id WHERE oi.order_id = :order_id");
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = 'Order not found.';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        error_log("Error fetching invoice data: " . $e->getMessage());
    }
} else {
    $message = 'No order ID provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <?php if ($order): ?>
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">INVOICE</h1>
                    <p class="text-gray-600">Invoice #: <span class="font-semibold"><?php echo htmlspecialchars($order['order_id']); ?></span></p>
                    <p class="text-gray-600">Date: <span class="font-semibold"><?php echo htmlspecialchars(date('Y-m-d', strtotime($order['order_date']))); ?></span></p>
                </div>
                <div class="text-right">
                    <h2 class="text-2xl font-bold text-gray-800">Your Company Name</h2>
                    <p class="text-gray-600">123 Business Rd.</p>
                    <p class="text-gray-600">Business City, BC 12345</p>
                    <p class="text-gray-600">Phone: (123) 456-7890</p>
                    <p class="text-gray-600">Email: info@yourcompany.com</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Bill To:</h2>
                    <p class="text-gray-700 font-semibold"><?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></p>
                    <p class="text-gray-700"><?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></p>
                    <p class="text-gray-700"><?php echo htmlspecialchars($customer['city'] ?? ''); ?>, <?php echo htmlspecialchars($customer['state'] ?? ''); ?> <?php echo htmlspecialchars($customer['zip_code'] ?? ''); ?></p>
                    <p class="text-gray-700">Phone: <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></p>
                    <p class="text-gray-700">Email: <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Payment Status:</h2>
                    <p class="text-2xl font-bold
                        <?php
                        if ($order['status'] === 'paid') echo 'text-green-600';
                        elseif ($order['status'] === 'unpaid') echo 'text-red-600';
                        else echo 'text-yellow-600';
                        ?>">
                        <?php echo htmlspecialchars(strtoupper($order['status'])); ?>
                    </p>
                    <p class="text-gray-700">Payment Method: <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
                </div>
            </div>

            <div class="mb-8">
                <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $i = 1; ?>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $i++; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$<?php echo htmlspecialchars(number_format($item['unit_price'], 2)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end mb-8">
                <div class="w-full max-w-xs">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700 font-semibold">Subtotal:</span>
                        <span class="text-gray-900 font-semibold">$<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></span>
                    </div>
                    <?php if ($order['vat_amount'] > 0): ?>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-700 font-semibold">VAT:</span>
                            <span class="text-gray-900 font-semibold">$<?php echo htmlspecialchars(number_format($order['vat_amount'], 2)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold border-t pt-2 mt-2 border-gray-300">
                        <span class="text-gray-800">Grand Total:</span>
                        <span class="text-blue-600">$<?php echo htmlspecialchars(number_format($order['grand_total'], 2)); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($order['notes']): ?>
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Notes:</h2>
                    <p class="text-gray-700"><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
            <?php endif; ?>

            <div class="text-center text-gray-600 text-sm mt-8">
                <p>Thank you for your business!</p>
                <p>Please make payment by the due date.</p>
            </div>

            <div class="no-print flex justify-center mt-8">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-print mr-2"></i> Print Invoice
                </button>
            </div>

        <?php else: ?>
            <div class="text-center text-red-600 text-lg">
                <p><?php echo htmlspecialchars($message); ?></p>
                <a href="/order/list.php" class="text-blue-600 hover:underline mt-4 block">Go back to Orders List</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
