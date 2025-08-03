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

$order = null;
$order_items = [];
$customers = [];
$inventory_items = [];
$message = '';
$message_type = '';

if (isset($_GET['id'])) {
    $order_id = $_GET['id'];
    try {
        // Fetch order details
        $stmt = $db->prepare("SELECT o.*, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE o.order_id = :order_id LIMIT 1");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $message = 'Order not found.';
            $message_type = 'error';
        } else {
            // Fetch order items
            $stmt = $db->prepare("SELECT oi.*, ii.name as item_name, ii.current_stock as available_stock FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id WHERE oi.order_id = :order_id");
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch all customers for dropdown
        $stmt = $db->query("SELECT customer_id, name FROM customers ORDER BY name ASC");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all inventory items for dropdown
        $stmt = $db->query("SELECT item_id, name, selling_price, current_stock FROM inventory_items WHERE status = 'active' ORDER BY name ASC");
        $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Error fetching order for edit: " . $e->getMessage());
    }
} else {
    $message = 'No order ID provided.';
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $order_id = $_POST['order_id'] ?? '';
    $customer_id = $_POST['customer_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $status = $_POST['status'] ?? 'unpaid';
    $notes = $_POST['notes'] ?? null;
    $vat_percentage = (float)($_POST['vat_percentage'] ?? 0);
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    if (empty($customer_id) || empty($item_ids)) {
        $message = 'Please select a customer and at least one item.';
        $message_type = 'error';
    } else {
        $new_total_amount = 0;
        $new_order_items_data = [];
        $stock_changes = []; // item_id => quantity_change (positive for increase, negative for decrease)

        // Get old order items for comparison
        $old_order_items = [];
        $stmt = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = :order_id");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $old_item) {
            $old_order_items[$old_item['item_id']] = $old_item['quantity'];
        }

        // Validate new items and calculate new subtotal
        foreach ($item_ids as $index => $itemId) {
            $quantity = (int)($quantities[$index] ?? 0);
            $unitPrice = (float)($unit_prices[$index] ?? 0);

            if ($quantity <= 0 || $unitPrice <= 0) {
                $message = 'Invalid quantity or unit price for an item.';
                $message_type = 'error';
                break;
            }

            // Calculate stock change for this item
            $old_quantity = $old_order_items[$itemId] ?? 0;
            $quantity_diff = $quantity - $old_quantity;

            // Fetch current stock to ensure availability for new quantity
            $stmt = $db->prepare("SELECT current_stock FROM inventory_items WHERE item_id = :item_id");
            $stmt->bindParam(':item_id', $itemId);
            $stmt->execute();
            $current_stock = $stmt->fetchColumn();

            if ($current_stock - $quantity_diff < 0) {
                $message = 'Not enough stock for item ' . htmlspecialchars($itemId) . '. Available: ' . $current_stock . ', Needed: ' . $quantity_diff;
                $message_type = 'error';
                break;
            }

            $new_total_amount += ($quantity * $unitPrice);
            $new_order_items_data[] = [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => ($quantity * $unitPrice)
            ];
            $stock_changes[$itemId] = ($stock_changes[$itemId] ?? 0) - $quantity_diff; // Negative means stock decreases
        }

        if ($message_type !== 'error') {
            $new_vat_amount = $new_total_amount * ($vat_percentage / 100);
            $new_grand_total = $new_total_amount + $new_vat_amount;

            try {
                $db->beginTransaction();

                // Update customer balance for old order amount
                $old_grand_total = $order['grand_total'];
                $old_status = $order['status'];
                $old_customer_id = $order['customer_id'];

                // Reverse old balance impact
                if ($old_status !== 'paid') { // Only reverse if it was unpaid/due
                    $stmt = $db->prepare("UPDATE customers SET balance = balance - :old_grand_total WHERE customer_id = :customer_id");
                    $stmt->bindParam(':old_grand_total', $old_grand_total);
                    $stmt->bindParam(':customer_id', $old_customer_id);
                    $stmt->execute();
                }
                // Reverse old total_purchases impact
                $stmt = $db->prepare("UPDATE customers SET total_purchases = total_purchases - :old_grand_total WHERE customer_id = :customer_id");
                $stmt->bindParam(':old_grand_total', $old_grand_total);
                $stmt->bindParam(':customer_id', $old_customer_id);
                $stmt->execute();


                // Update orders table
                $stmt = $db->prepare("UPDATE orders SET customer_id = :customer_id, total_amount = :total_amount, vat_amount = :vat_amount, grand_total = :grand_total, payment_method = :payment_method, status = :status, notes = :notes WHERE order_id = :order_id");
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':total_amount', $new_total_amount);
                $stmt->bindParam(':vat_amount', $new_vat_amount);
                $stmt->bindParam(':grand_total', $new_grand_total);
                $stmt->bindParam(':payment_method', $payment_method);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();

                // Delete old order items
                $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = :order_id");
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();

                // Insert new order items and update inventory
                foreach ($new_order_items_data as $item) {
                    $stmt = $db->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, subtotal) VALUES (:order_id, :item_id, :quantity, :unit_price, :subtotal)");
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':unit_price', $item['unit_price']);
                    $stmt->bindParam(':subtotal', $item['subtotal']);
                    $stmt->execute();
                }

                // Apply stock changes
                foreach ($stock_changes as $itemId => $change) {
                    $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock + :change WHERE item_id = :item_id");
                    $stmt->bindParam(':change', $change, PDO::PARAM_INT);
                    $stmt->bindParam(':item_id', $itemId);
                    $stmt->execute();

                    // Log inventory transaction for adjustment (optional)
                    if ($change !== 0) {
                        $transaction_id = 'TRN' . strtoupper(uniqid());
                        $transaction_type = $change < 0 ? 'sale' : 'return'; // If change is negative, it's a sale; positive, it's a return
                        $stmt = $db->prepare("INSERT INTO inventory_transactions (transaction_id, item_id, transaction_type, quantity, unit_cost, total_cost, reference_id, reference_type, customer_id, transaction_date, created_by) SELECT :transaction_id, :item_id, :transaction_type, :quantity, unit_cost, :total_cost, :reference_id, 'order_edit', :customer_id, CURDATE(), :created_by FROM inventory_items WHERE item_id = :item_id_for_cost");
                        $abs_change = abs($change);
                        $total_cost_for_log = $abs_change * ($item['unit_price'] ?? 0); // Use the new unit price for logging
                        $stmt->bindParam(':transaction_id', $transaction_id);
                        $stmt->bindParam(':item_id', $itemId);
                        $stmt->bindParam(':transaction_type', $transaction_type);
                        $stmt->bindParam(':quantity', $abs_change, PDO::PARAM_INT);
                        $stmt->bindParam(':total_cost', $total_cost_for_log);
                        $stmt->bindParam(':reference_id', $order_id);
                        $stmt->bindParam(':customer_id', $customer_id);
                        $stmt->bindParam(':created_by', $_SESSION['admin_name']);
                        $stmt->bindParam(':item_id_for_cost', $itemId);
                        $stmt->execute();
                    }
                }

                // Update customer total purchases and balance for new order amount
                $stmt = $db->prepare("UPDATE customers SET total_purchases = total_purchases + :new_grand_total, balance = balance + :new_balance_change WHERE customer_id = :customer_id");
                $new_balance_change = ($status === 'paid') ? 0 : $new_grand_total;
                $stmt->bindParam(':new_grand_total', $new_grand_total);
                $stmt->bindParam(':new_balance_change', $new_balance_change);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->execute();


                $db->commit();
                $message = 'Order updated successfully!';
                $message_type = 'success';
                // Redirect to order list or receipt page
                header('Location: /order/list.php?message=' . urlencode($message) . '&type=' . $message_type);
                exit();

            } catch (PDOException $e) {
                $db->rollBack();
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error updating order: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order - Billing System</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Order: <?php echo htmlspecialchars($order['order_id'] ?? ''); ?></h1>

            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <form action="edit-order.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" method="POST" id="orderForm">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="customer_id" class="block text-gray-700 text-sm font-bold mb-2">Customer Name <span class="text-red-500">*</span></label>
                                <select id="customer_id" name="customer_id" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer_option): ?>
                                        <option value="<?php echo htmlspecialchars($customer_option['customer_id']); ?>"
                                                <?php echo ($order['customer_id'] === $customer_option['customer_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer_option['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                                <select id="payment_method" name="payment_method"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                    <option value="cash" <?php echo ($order['payment_method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="mobilebanking" <?php echo ($order['payment_method'] === 'mobilebanking') ? 'selected' : ''; ?>>Mobile Banking</option>
                                    <option value="esawa" <?php echo ($order['payment_method'] === 'esawa') ? 'selected' : ''; ?>>eSewa</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Payment Status</label>
                                <select id="status" name="status"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                    <option value="unpaid" <?php echo ($order['status'] === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                    <option value="paid" <?php echo ($order['status'] === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="due" <?php echo ($order['status'] === 'due') ? 'selected' : ''; ?>>Due</option>
                                </select>
                            </div>
                            <div>
                                <label for="vat_percentage" class="block text-gray-700 text-sm font-bold mb-2">VAT (%) (Optional)</label>
                                <input type="number" id="vat_percentage" name="vat_percentage" value="<?php echo htmlspecialchars($order['vat_amount'] > 0 ? round(($order['vat_amount'] / $order['total_amount']) * 100, 2) : 0); ?>" min="0" max="100" step="0.01"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Order Items</h3>
                        <div id="order-items-container">
                            <?php if (!empty($order_items)): ?>
                                <?php foreach ($order_items as $index => $item): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-2 border rounded-md bg-gray-50 item-row">
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Product</label>
                                            <select name="item_id[]" class="item-select shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($inventory_items as $inv_item): ?>
                                                    <option value="<?php echo htmlspecialchars($inv_item['item_id']); ?>"
                                                            data-price="<?php echo htmlspecialchars($inv_item['selling_price']); ?>"
                                                            data-stock="<?php echo htmlspecialchars($inv_item['current_stock'] + ($item['item_id'] === $inv_item['item_id'] ? $item['quantity'] : 0)); ?>"
                                                            <?php echo ($item['item_id'] === $inv_item['item_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($inv_item['name']); ?> (Stock: <?php echo htmlspecialchars($inv_item['current_stock'] + ($item['item_id'] === $inv_item['item_id'] ? $item['quantity'] : 0)); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                                            <input type="number" name="quantity[]" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" required
                                                   class="item-quantity shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2">Unit Price</label>
                                            <input type="number" name="unit_price[]" value="<?php echo htmlspecialchars(number_format($item['unit_price'], 2, '.', '')); ?>" step="0.01" min="0" required readonly
                                                   class="item-unit-price shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 bg-gray-200">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">Remove</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-600 text-center">No items in this order. Add items below.</p>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="add-item-btn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mt-4">Add Item</button>

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-bold">Subtotal:</span>
                                <span id="subtotal-display" class="text-gray-900 font-bold">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-bold">VAT (<span id="vat-percent-display">0</span>%):</span>
                                <span id="vat-amount-display" class="text-gray-900 font-bold">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xl font-bold mb-4">
                                <span class="text-gray-800">Grand Total:</span>
                                <span id="grand-total-display" class="text-blue-600">$0.00</span>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="notes" class="block text-gray-700 text-sm font-bold mb-2">Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3"
                                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Update Order
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif (!$message): ?>
                <p class="text-gray-600 text-center">Loading order data...</p>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const orderItemsContainer = document.getElementById('order-items-container');
            const addItemBtn = document.getElementById('add-item-btn');
            const vatPercentageInput = document.getElementById('vat_percentage');

            const inventoryItemsData = <?php echo json_encode($inventory_items); ?>;

            function calculateTotals() {
                let subtotal = 0;
                orderItemsContainer.querySelectorAll('.item-row').forEach(row => {
                    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                    subtotal += (quantity * unitPrice);
                });

                const vatPercent = parseFloat(vatPercentageInput.value) || 0;
                const vatAmount = subtotal * (vatPercent / 100);
                const grandTotal = subtotal + vatAmount;

                document.getElementById('subtotal-display').textContent = `$${subtotal.toFixed(2)}`;
                document.getElementById('vat-percent-display').textContent = vatPercent.toFixed(0);
                document.getElementById('vat-amount-display').textContent = `$${vatAmount.toFixed(2)}`;
                document.getElementById('grand-total-display').textContent = `$${grandTotal.toFixed(2)}`;
            }

            function updateItemPriceAndStock(selectElement) {
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const row = selectElement.closest('.item-row');
                const unitPriceInput = row.querySelector('.item-unit-price');
                const quantityInput = row.querySelector('.item-quantity');

                if (selectedOption.value) {
                    const price = parseFloat(selectedOption.dataset.price);
                    const stock = parseInt(selectedOption.dataset.stock);
                    unitPriceInput.value = price.toFixed(2);
                    quantityInput.max = stock; // Set max quantity to available stock
                    if (parseInt(quantityInput.value) > stock) {
                        quantityInput.value = stock; // Adjust quantity if it exceeds stock
                    }
                } else {
                    unitPriceInput.value = '0.00';
                    quantityInput.max = ''; // Remove max if no item selected
                }
                calculateTotals();
            }

            function createItemRow() {
                const itemRow = document.createElement('div');
                itemRow.classList.add('grid', 'grid-cols-1', 'md:grid-cols-4', 'gap-4', 'mb-4', 'p-2', 'border', 'rounded-md', 'bg-gray-50', 'item-row');
                itemRow.innerHTML = `
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Product</label>
                        <select name="item_id[]" class="item-select shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                            <option value="">Select Product</option>
                            <?php foreach ($inventory_items as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['item_id']); ?>"
                                        data-price="<?php echo htmlspecialchars($item['selling_price']); ?>"
                                        data-stock="<?php echo htmlspecialchars($item['current_stock']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?> (Stock: <?php echo htmlspecialchars($item['current_stock']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                        <input type="number" name="quantity[]" value="1" min="1" required
                               class="item-quantity shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Unit Price</label>
                        <input type="number" name="unit_price[]" value="0.00" step="0.01" min="0" required readonly
                               class="item-unit-price shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 bg-gray-200">
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">Remove</button>
                    </div>
                `;
                orderItemsContainer.appendChild(itemRow);
                attachEventListeners(itemRow);
                calculateTotals();
            }

            function attachEventListeners(row) {
                const selectElement = row.querySelector('.item-select');
                const quantityInput = row.querySelector('.item-quantity');
                const removeButton = row.querySelector('.remove-item-btn');

                selectElement.addEventListener('change', () => updateItemPriceAndStock(selectElement));
                quantityInput.addEventListener('input', calculateTotals);
                removeButton.addEventListener('click', () => {
                    row.remove();
                    calculateTotals();
                });
            }

            // Attach event listeners to initial rows
            orderItemsContainer.querySelectorAll('.item-row').forEach(attachEventListeners);

            addItemBtn.addEventListener('click', createItemRow);
            vatPercentageInput.addEventListener('input', calculateTotals);

            // Initial calculation
            calculateTotals();
        });
    </script>
</body>
</html>
