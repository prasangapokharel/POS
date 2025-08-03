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

$message = '';
$message_type = '';

// Fetch customers and inventory items for dropdowns
$customers = [];
$inventory_items = [];
try {
    $stmt = $db->query("SELECT customer_id, name FROM customers ORDER BY name ASC");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT item_id, name, selling_price, current_stock FROM inventory_items WHERE status = 'active' AND current_stock > 0 ORDER BY name ASC");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching data for new order: " . $e->getMessage());
    $message = 'Error loading data: ' . $e->getMessage();
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $total_amount = 0;
        $order_items_data = [];
        $stock_updates = [];

        // Validate items and calculate subtotal
        foreach ($item_ids as $index => $itemId) {
            $quantity = (int)($quantities[$index] ?? 0);
            $unitPrice = (float)($unit_prices[$index] ?? 0);

            if ($quantity <= 0 || $unitPrice <= 0) {
                $message = 'Invalid quantity or unit price for an item.';
                $message_type = 'error';
                break;
            }

            // Fetch current stock to ensure availability
            $stmt = $db->prepare("SELECT current_stock FROM inventory_items WHERE item_id = :item_id");
            $stmt->bindParam(':item_id', $itemId);
            $stmt->execute();
            $item_stock = $stmt->fetchColumn();

            if ($item_stock < $quantity) {
                $message = 'Not enough stock for item ' . htmlspecialchars($itemId) . '. Available: ' . $item_stock;
                $message_type = 'error';
                break;
            }

            $subtotal = $quantity * $unitPrice;
            $total_amount += $subtotal;
            $order_items_data[] = [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal
            ];
            $stock_updates[$itemId] = ($stock_updates[$itemId] ?? 0) + $quantity;
        }

        if ($message_type !== 'error') {
            $vat_amount = $total_amount * ($vat_percentage / 100);
            $grand_total = $total_amount + $vat_amount;
            $order_id = 'ORD' . strtoupper(uniqid());

            try {
                $db->beginTransaction();

                // Insert into orders table
                $stmt = $db->prepare("INSERT INTO orders (order_id, customer_id, total_amount, vat_amount, grand_total, payment_method, status, notes) VALUES (:order_id, :customer_id, :total_amount, :vat_amount, :grand_total, :payment_method, :status, :notes)");
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':total_amount', $total_amount);
                $stmt->bindParam(':vat_amount', $vat_amount);
                $stmt->bindParam(':grand_total', $grand_total);
                $stmt->bindParam(':payment_method', $payment_method);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':notes', $notes);
                $stmt->execute();

                // Insert into order_items and update inventory
                foreach ($order_items_data as $item) {
                    $stmt = $db->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, subtotal) VALUES (:order_id, :item_id, :quantity, :unit_price, :subtotal)");
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':unit_price', $item['unit_price']);
                    $stmt->bindParam(':subtotal', $item['subtotal']);
                    $stmt->execute();

                    // Deduct quantity from inventory
                    $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock - :quantity WHERE item_id = :item_id");
                    $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->execute();

                    // Log inventory transaction (optional, but good practice)
                    $transaction_id = 'TRN' . strtoupper(uniqid());
                    $stmt = $db->prepare("INSERT INTO inventory_transactions (transaction_id, item_id, transaction_type, quantity, unit_cost, total_cost, reference_id, reference_type, customer_id, transaction_date, created_by) SELECT :transaction_id, :item_id, 'sale', :quantity, unit_cost, :total_cost, :reference_id, 'order', :customer_id, CURDATE(), :created_by FROM inventory_items WHERE item_id = :item_id_for_cost");
                    $stmt->bindParam(':transaction_id', $transaction_id);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':total_cost', $item['subtotal']);
                    $stmt->bindParam(':reference_id', $order_id);
                    $stmt->bindParam(':customer_id', $customer_id);
                    $stmt->bindParam(':created_by', $_SESSION['admin_name']);
                    $stmt->bindParam(':item_id_for_cost', $item['item_id']);
                    $stmt->execute();
                }

                // Update customer total purchases and balance
                $stmt = $db->prepare("UPDATE customers SET total_purchases = total_purchases + :grand_total, balance = balance + :balance_change WHERE customer_id = :customer_id");
                $balance_change = ($status === 'paid') ? 0 : $grand_total; // If paid, balance change is 0, else it's the grand total
                $stmt->bindParam(':grand_total', $grand_total);
                $stmt->bindParam(':balance_change', $balance_change);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->execute();

                $db->commit();
                $message = 'Order created successfully! Order ID: ' . $order_id;
                $message_type = 'success';
                // Redirect to order list or receipt page
                header('Location: /order/list.php?message=' . urlencode($message) . '&type=' . $message_type);
                exit();

            } catch (PDOException $e) {
                $db->rollBack();
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error creating order: " . $e->getMessage());
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
    <title>New Order - Billing System</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Create New Order</h1>

            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <form action="new-order.php" method="POST" id="orderForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="customer_id" class="block text-gray-700 text-sm font-bold mb-2">Customer Name <span class="text-red-500">*</span></label>
                            <select id="customer_id" name="customer_id" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                            <select id="payment_method" name="payment_method"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                <option value="cash">Cash</option>
                                <option value="mobilebanking">Mobile Banking</option>
                                <option value="esawa">eSewa</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Payment Status</label>
                            <select id="status" name="status"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="due">Due</option>
                            </select>
                        </div>
                        <div>
                            <label for="vat_percentage" class="block text-gray-700 text-sm font-bold mb-2">VAT (%) (Optional)</label>
                            <input type="number" id="vat_percentage" name="vat_percentage" value="0" min="0" max="100" step="0.01"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Order Items</h3>
                    <div id="order-items-container">
                        <!-- Item rows will be added here by JavaScript -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-2 border rounded-md bg-gray-50 item-row">
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
                        </div>
                    </div>
                    <button type="button" id="add-item-btn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mt-4">Add Item</button>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 font-bold">Subtotal:</span>
                            <span id="subtotal-display" class="text-gray-900 font-bold">Rs0.00</span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 font-bold">VAT (<span id="vat-percent-display">0</span>%):</span>
                            <span id="vat-amount-display" class="text-gray-900 font-bold">Rs0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-xl font-bold mb-4">
                            <span class="text-gray-800">Grand Total:</span>
                            <span id="grand-total-display" class="text-blue-600">Rs0.00</span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="notes" class="block text-gray-700 text-sm font-bold mb-2">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit"
                                class="bg-primary  text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Create Order
                        </button>
                    </div>
                </form>
            </div>
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

                document.getElementById('subtotal-display').textContent = `Rs${subtotal.toFixed(2)}`;
                document.getElementById('vat-percent-display').textContent = vatPercent.toFixed(0);
                document.getElementById('vat-amount-display').textContent = `Rs${vatAmount.toFixed(2)}`;
                document.getElementById('grand-total-display').textContent = `Rs${grandTotal.toFixed(2)}`;
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

            // Attach event listeners to initial row
            orderItemsContainer.querySelectorAll('.item-row').forEach(attachEventListeners);

            addItemBtn.addEventListener('click', createItemRow);
            vatPercentageInput.addEventListener('input', calculateTotals);

            // Initial calculation
            calculateTotals();
        });
    </script>
</body>
</html>
