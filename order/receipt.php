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
        error_log("Error fetching receipt data: " . $e->getMessage());
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
    <title>Receipt - <?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></title>
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
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <?php if ($order): ?>
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Receipt</h1>
                <p class="text-gray-600">Order ID: <span class="font-semibold"><?php echo htmlspecialchars($order['order_id']); ?></span></p>
                <p class="text-gray-600">Date: <span class="font-semibold"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></span></p>
            </div>

            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Customer Information</h2>
                <p class="text-gray-700"><strong>Name:</strong> <?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></p>
                <p class="text-gray-700"><strong>Contact:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></p>
                <p class="text-gray-700"><strong>Email:</strong> <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p>
                <p class="text-gray-700"><strong>Address:</strong> <?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($customer['city'] ?? ''); ?></p>
            </div>

            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Details</h2>
                <table class="min-w-full divide-y divide-gray-200 mb-4">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo htmlspecialchars(number_format($item['unit_price'], 2)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="flex justify-end flex-col items-end text-right">
                    <div class="flex justify-between w-full max-w-xs mb-2">
                        <span class="text-gray-700 font-semibold">Subtotal:</span>
                        <span class="text-gray-900 font-semibold">$<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></span>
                    </div>
                    <?php if ($order['vat_amount'] > 0): ?>
                        <div class="flex justify-between w-full max-w-xs mb-2">
                            <span class="text-gray-700 font-semibold">VAT:</span>
                            <span class="text-gray-900 font-semibold">$<?php echo htmlspecialchars(number_format($order['vat_amount'], 2)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between w-full max-w-xs text-xl font-bold border-t pt-2 mt-2 border-gray-300">
                        <span class="text-gray-800">Grand Total:</span>
                        <span class="text-blue-600">$<?php echo htmlspecialchars(number_format($order['grand_total'], 2)); ?></span>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <p class="text-gray-700"><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
                <p class="text-gray-700"><strong>Payment Status:</strong>
                    <span class="font-semibold
                        <?php
                        if ($order['status'] === 'paid') echo 'text-green-600';
                        elseif ($order['status'] === 'unpaid') echo 'text-red-600';
                        else echo 'text-yellow-600';
                        ?>">
                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                    </span>
                </p>
                <?php if ($order['notes']): ?>
                    <p class="text-gray-700"><strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                <?php endif; ?>
            </div>

            <div class="text-center text-gray-600 text-sm mt-8">
                <p>Thank you for your business!</p>
                <p>This is a computer generated receipt and does not require a signature.</p>
            </div>

            <div class="no-print flex justify-center mt-8">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-print mr-2"></i> Print Receipt
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
