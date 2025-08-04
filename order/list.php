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

$orders = [];
$message = '';
$message_type = '';

// Check for messages from redirects
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type'] ?? 'info');
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $order_id_to_delete = $_GET['id'];
    try {
        $db->beginTransaction();

        // Get order details to reverse stock and customer balance
        $stmt = $db->prepare("SELECT o.grand_total, o.status, o.customer_id, oi.item_id, oi.quantity, oi.unit_price FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.order_id = :order_id");
        $stmt->bindParam(':order_id', $order_id_to_delete);
        $stmt->execute();
        $order_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($order_details)) {
            $grand_total = $order_details[0]['grand_total'];
            $order_status = $order_details[0]['status'];
            $customer_id = $order_details[0]['customer_id'];

            // Reverse customer total purchases and balance
            $stmt = $db->prepare("UPDATE customers SET total_purchases = total_purchases - :grand_total, balance = balance - :balance_change WHERE customer_id = :customer_id");
            $balance_change = ($order_status === 'paid') ? 0 : $grand_total;
            $stmt->bindParam(':grand_total', $grand_total);
            $stmt->bindParam(':balance_change', $balance_change);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();

            // Return items to inventory
            foreach ($order_details as $detail) {
                $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock + :quantity WHERE item_id = :item_id");
                $stmt->bindParam(':quantity', $detail['quantity'], PDO::PARAM_INT);
                $stmt->bindParam(':item_id', $detail['item_id']);
                $stmt->execute();

                // Log inventory transaction for return (optional)
                $transaction_id = 'TRN' . strtoupper(uniqid());
                $stmt = $db->prepare("INSERT INTO inventory_transactions (transaction_id, item_id, transaction_type, quantity, unit_cost, total_cost, reference_id, reference_type, customer_id, transaction_date, created_by) SELECT :transaction_id, :item_id, 'return', :quantity, unit_cost, :total_cost, :reference_id, 'order_return', :customer_id, CURDATE(), :created_by FROM inventory_items WHERE item_id = :item_id_for_cost");
                $stmt->bindParam(':transaction_id', $transaction_id);
                $stmt->bindParam(':item_id', $detail['item_id']);
                $stmt->bindParam(':quantity', $detail['quantity'], PDO::PARAM_INT);
                $total_cost_for_log = $detail['quantity'] * $detail['unit_price'];
                $stmt->bindParam(':total_cost', $total_cost_for_log);
                $stmt->bindParam(':reference_id', $order_id_to_delete);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':created_by', $_SESSION['admin_name']);
                $stmt->bindParam(':item_id_for_cost', $detail['item_id']);
                $stmt->execute();
            }
        }

        // Delete the order
        $stmt = $db->prepare("DELETE FROM orders WHERE order_id = :order_id");
        $stmt->bindParam(':order_id', $order_id_to_delete);
        if ($stmt->execute()) {
            $db->commit();
            $message = 'Order deleted successfully!';
            $message_type = 'success';
        } else {
            $db->rollBack();
            $message = 'Failed to delete order.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Error deleting order: " . $e->getMessage());
    }
}

// Fetch all orders with customer names
try {
    $stmt = $db->query("SELECT o.*, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.customer_id ORDER BY o.order_date DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error fetching orders: ' . $e->getMessage();
    $message_type = 'error';
    error_log("Error fetching orders: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders List - Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
    <?php include __DIR__ . '/../component/navbar.php'; ?>
    <div class="flex">
        <?php include __DIR__ . '/../component/sidebar.php'; ?>
        <main class="flex-1">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Orders List</h1>
                <a href="/order/new-order.php" class="bg-primary text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Create New Order
                </a>
            </div>

            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
                <?php if (empty($orders)): ?>
                    <p class="text-gray-600 text-center">No orders found. Create a new order to get started.</p>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('Y-m-d', strtotime($order['order_date']))); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Rs<?php echo htmlspecialchars(number_format($order['grand_total'], 2)); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?php
                                            if ($order['status'] === 'paid') echo 'bg-green-100 text-green-800';
                                            elseif ($order['status'] === 'unpaid') echo 'bg-red-100 text-red-800';
                                            else echo 'bg-yellow-100 text-yellow-800';
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="/order/edit-order.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                        <a href="/order/receipt.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" target="_blank" class="text-purple-600 hover:text-purple-900 mr-3">Receipt</a>
                                        <a href="/order/invoice.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 mr-3">Invoice</a>
                                        <a href="/order/list.php?action=delete&id=<?php echo htmlspecialchars($order['order_id']); ?>"
                                           onclick="return confirm('Are you sure you want to delete this order? This will return items to stock.');"
                                           class="text-red-600 hover:text-red-900">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
