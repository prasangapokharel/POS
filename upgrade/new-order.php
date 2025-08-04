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
    $new_customer_name = trim($_POST['new_customer_name'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $status = $_POST['status'] ?? 'unpaid';
    $notes = $_POST['notes'] ?? null;
    $vat_percentage = (float)($_POST['vat_percentage'] ?? 0);
    $discount_type = $_POST['discount_type'] ?? 'none';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    // Handle quick customer creation
    if (empty($customer_id) && !empty($new_customer_name)) {
        try {
            // Generate unique customer ID
            $new_customer_id = 'CUST' . strtoupper(substr(uniqid(), -6));
            
            // Insert new customer
            $stmt = $db->prepare("INSERT INTO customers (customer_id, name, status) VALUES (:customer_id, :name, 'active')");
            $stmt->bindParam(':customer_id', $new_customer_id);
            $stmt->bindParam(':name', $new_customer_name);
            $stmt->execute();
            
            $customer_id = $new_customer_id;
            $message = 'New customer created: ' . $new_customer_name . ' (ID: ' . $new_customer_id . ')';
            $message_type = 'info';
        } catch (PDOException $e) {
            $message = 'Error creating new customer: ' . $e->getMessage();
            $message_type = 'error';
            error_log("Error creating customer: " . $e->getMessage());
        }
    }

    if (empty($customer_id) || empty($item_ids)) {
        if (empty($message)) {
            $message = 'Please select a customer or enter a new customer name, and at least one item.';
            $message_type = 'error';
        }
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
            // Calculate discount
            $discount_amount = 0;
            if ($discount_type === 'percentage' && $discount_value > 0) {
                $discount_amount = $total_amount * ($discount_value / 100);
            } elseif ($discount_type === 'fixed' && $discount_value > 0) {
                $discount_amount = min($discount_value, $total_amount); // Don't allow discount greater than total
            }

            $discounted_amount = $total_amount - $discount_amount;
            $vat_amount = $discounted_amount * ($vat_percentage / 100);
            $grand_total = $discounted_amount + $vat_amount;
            $order_id = 'ORD' . strtoupper(uniqid());

            try {
                $db->beginTransaction();

                // Insert into orders table (including discount_amount)
                $stmt = $db->prepare("INSERT INTO orders (order_id, customer_id, total_amount, discount_amount, vat_amount, grand_total, payment_method, status, notes) VALUES (:order_id, :customer_id, :total_amount, :discount_amount, :vat_amount, :grand_total, :payment_method, :status, :notes)");
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':total_amount', $total_amount);
                $stmt->bindParam(':discount_amount', $discount_amount);
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

                    // Log inventory transaction
                    $transaction_id = 'TRN' . strtoupper(uniqid());
                    $stmt = $db->prepare("INSERT INTO inventory_transactions (transaction_id, item_id, transaction_type, quantity, unit_cost, total_cost, reference_id, reference_type, customer_id, transaction_date, created_by) SELECT :transaction_id, :item_id, 'sale', :quantity, unit_cost, :total_cost, :reference_id, 'order', :customer_id, CURDATE(), :created_by FROM inventory_items WHERE item_id = :item_id_for_cost");
                    $stmt->bindParam(':transaction_id', $transaction_id);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':total_cost', $item['subtotal']);
                    $stmt->bindParam(':reference_id', $order_id);
                    $stmt->bindParam(':customer_id', $customer_id);
                    $stmt->bindParam(':created_by', $admin_info['name']);
                    $stmt->bindParam(':item_id_for_cost', $item['item_id']);
                    $stmt->execute();
                }

                // Update customer total purchases and balance
                $stmt = $db->prepare("UPDATE customers SET total_purchases = total_purchases + :grand_total, balance = balance + :balance_change WHERE customer_id = :customer_id");
                $balance_change = ($status === 'paid') ? 0 : $grand_total;
                $stmt->bindParam(':grand_total', $grand_total);
                $stmt->bindParam(':balance_change', $balance_change);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->execute();

                $db->commit();
                
                $success_message = 'Order created successfully! Order ID: ' . $order_id;
                if (!empty($new_customer_name)) {
                    $success_message .= ' | New customer: ' . $new_customer_name;
                }
                if ($discount_amount > 0) {
                    $success_message .= ' | Discount applied: ₹' . number_format($discount_amount, 2);
                }
                
                header('Location: /order/list.php?message=' . urlencode($success_message) . '&type=success');
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            font-weight: 400;
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
<body class="bg-gray-50">
    <?php include __DIR__ . '/../component/navbar.php'; ?>
    
    <div class="flex">
        <?php include __DIR__ . '/../component/sidebar.php'; ?>
        
        <main class="flex-1">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800 mb-2">Create New Order</h1>
                <p class="text-gray-600 text-sm">Add items and create order for existing or new customer</p>
            </div>

            <?php if ($message): ?>
                <div class="p-4 mb-6 text-sm rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : ($message_type === 'info' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-red-50 text-red-700 border border-red-200'); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <form action="new-order.php" method="POST" id="orderForm">
                    <!-- Customer Selection Section -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Customer Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="customer_id" class="block text-gray-700 text-sm font-medium mb-2">Select Existing Customer</label>
                                <select id="customer_id" name="customer_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Choose existing customer...</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>">
                                            <?php echo htmlspecialchars($customer['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="new_customer_name" class="block text-gray-700 text-sm font-medium mb-2">Or Create New Customer</label>
                                <input type="text" id="new_customer_name" name="new_customer_name" placeholder="Enter full name for new customer"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <p class="text-sm text-gray-500 mt-1">Leave empty if using existing customer</p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="payment_method" class="block text-gray-700 text-sm font-medium mb-2">Payment Method</label>
                            <select id="payment_method" name="payment_method"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="cash">Cash</option>
                                <option value="mobilebanking">Mobile Banking</option>
                                <option value="esawa">eSewa</option>
                                <option value="khalti">Khalti</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-gray-700 text-sm font-medium mb-2">Payment Status</label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="due">Due</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="vat_percentage" class="block text-gray-700 text-sm font-medium mb-2">VAT (%)</label>
                            <input type="number" id="vat_percentage" name="vat_percentage" value="0" min="0" max="100" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    <!-- Discount Section -->
                    <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Discount (Optional)</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="discount_type" class="block text-gray-700 text-sm font-medium mb-2">Discount Type</label>
                                <select id="discount_type" name="discount_type"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="none">No Discount</option>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₹)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="discount_value" class="block text-gray-700 text-sm font-medium mb-2">Discount Value</label>
                                <input type="number" id="discount_value" name="discount_value" value="0" min="0" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Enter discount value">
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Order Items</h3>
                        
                        <div id="order-items-container">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4 p-4 border border-gray-300 rounded-lg bg-gray-50 item-row">
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Product</label>
                                    <select name="item_id[]" class="item-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
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
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Quantity</label>
                                    <input type="number" name="quantity[]" value="1" min="1" required
                                           class="item-quantity w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Unit Price</label>
                                    <input type="number" name="unit_price[]" value="0.00" step="0.01" min="0" required readonly
                                           class="item-unit-price w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Subtotal</label>
                                    <input type="text" class="item-subtotal w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly value="₹0.00">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-medium px-4 py-2 rounded-md text-sm w-full">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add-item-btn" class="bg-accent hover:bg-accent-dark text-white font-medium px-4 py-2 rounded-md text-sm">
                            Add Another Item
                        </button>
                    </div>

                    <!-- Order Summary -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">Order Summary</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 font-medium">Subtotal:</span>
                                <span id="subtotal-display" class="text-gray-800 font-medium">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-sm" id="discount-row" style="display: none;">
                                <span class="text-gray-600 font-medium">Discount (<span id="discount-type-display"></span>):</span>
                                <span id="discount-amount-display" class="text-red-600 font-medium">-₹0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 font-medium">After Discount:</span>
                                <span id="after-discount-display" class="text-gray-800 font-medium">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 font-medium">VAT (<span id="vat-percent-display">0</span>%):</span>
                                <span id="vat-amount-display" class="text-gray-800 font-medium">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold border-t border-gray-200 pt-3">
                                <span class="text-gray-800">Grand Total:</span>
                                <span id="grand-total-display" class="text-primary">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label for="notes" class="block text-gray-700 text-sm font-medium mb-2">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Add any additional notes..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-medium px-6 py-3 rounded-md text-sm">
                            Create Order
                        </button>
                    </div>
                </form>
            </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            const orderItemsContainer = document.getElementById('order-items-container');
            const addItemBtn = document.getElementById('add-item-btn');
            const vatPercentageInput = document.getElementById('vat_percentage');
            const customerSelect = document.getElementById('customer_id');
            const newCustomerInput = document.getElementById('new_customer_name');
            const discountTypeSelect = document.getElementById('discount_type');
            const discountValueInput = document.getElementById('discount_value');

            // Customer selection logic
            customerSelect.addEventListener('change', function() {
                if (this.value) {
                    newCustomerInput.value = '';
                    newCustomerInput.disabled = true;
                } else {
                    newCustomerInput.disabled = false;
                }
            });

            newCustomerInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    customerSelect.value = '';
                    customerSelect.disabled = true;
                } else {
                    customerSelect.disabled = false;
                }
            });

            // Discount type change handler
            discountTypeSelect.addEventListener('change', function() {
                const discountRow = document.getElementById('discount-row');
                if (this.value === 'none') {
                    discountValueInput.value = '0';
                    discountValueInput.disabled = true;
                    discountRow.style.display = 'none';
                } else {
                    discountValueInput.disabled = false;
                    discountRow.style.display = 'flex';
                }
                calculateTotals();
            });

            function calculateTotals() {
                let subtotal = 0;
                
                orderItemsContainer.querySelectorAll('.item-row').forEach(row => {
                    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                    const itemSubtotal = quantity * unitPrice;
                    
                    row.querySelector('.item-subtotal').value = `₹${itemSubtotal.toFixed(2)}`;
                    subtotal += itemSubtotal;
                });

                // Calculate discount
                const discountType = discountTypeSelect.value;
                const discountValue = parseFloat(discountValueInput.value) || 0;
                let discountAmount = 0;

                if (discountType === 'percentage' && discountValue > 0) {
                    discountAmount = subtotal * (discountValue / 100);
                } else if (discountType === 'fixed' && discountValue > 0) {
                    discountAmount = Math.min(discountValue, subtotal);
                }

                const afterDiscount = subtotal - discountAmount;
                const vatPercent = parseFloat(vatPercentageInput.value) || 0;
                const vatAmount = afterDiscount * (vatPercent / 100);
                const grandTotal = afterDiscount + vatAmount;

                // Update displays
                document.getElementById('subtotal-display').textContent = `₹${subtotal.toFixed(2)}`;
                document.getElementById('after-discount-display').textContent = `₹${afterDiscount.toFixed(2)}`;
                document.getElementById('vat-percent-display').textContent = vatPercent.toFixed(0);
                document.getElementById('vat-amount-display').textContent = `₹${vatAmount.toFixed(2)}`;
                document.getElementById('grand-total-display').textContent = `₹${grandTotal.toFixed(2)}`;

                // Update discount display
                if (discountAmount > 0) {
                    document.getElementById('discount-row').style.display = 'flex';
                    document.getElementById('discount-amount-display').textContent = `-₹${discountAmount.toFixed(2)}`;
                    
                    let discountTypeText = '';
                    if (discountType === 'percentage') {
                        discountTypeText = `${discountValue}%`;
                    } else if (discountType === 'fixed') {
                        discountTypeText = `₹${discountValue}`;
                    }
                    document.getElementById('discount-type-display').textContent = discountTypeText;
                } else {
                    document.getElementById('discount-row').style.display = 'none';
                }
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
                    quantityInput.max = stock;
                    
                    if (parseInt(quantityInput.value) > stock) {
                        quantityInput.value = stock;
                    }
                } else {
                    unitPriceInput.value = '0.00';
                    quantityInput.max = '';
                }
                calculateTotals();
            }

            function createItemRow() {
                const itemRow = document.createElement('div');
                itemRow.classList.add('grid', 'grid-cols-1', 'md:grid-cols-5', 'gap-4', 'mb-4', 'p-4', 'border', 'border-gray-300', 'rounded-lg', 'bg-gray-50', 'item-row');
                
                itemRow.innerHTML = `
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Product</label>
                        <select name="item_id[]" class="item-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
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
                        <label class="block text-gray-700 text-sm font-medium mb-2">Quantity</label>
                        <input type="number" name="quantity[]" value="1" min="1" required
                               class="item-quantity w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Unit Price</label>
                        <input type="number" name="unit_price[]" value="0.00" step="0.01" min="0" required readonly
                               class="item-unit-price w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Subtotal</label>
                        <input type="text" class="item-subtotal w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly value="₹0.00">
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-medium px-4 py-2 rounded-md text-sm w-full">
                            Remove
                        </button>
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
                    if (orderItemsContainer.querySelectorAll('.item-row').length > 1) {
                        row.remove();
                        calculateTotals();
                    }
                });
            }

            // Attach event listeners to initial row
            orderItemsContainer.querySelectorAll('.item-row').forEach(attachEventListeners);

            addItemBtn.addEventListener('click', createItemRow);
            vatPercentageInput.addEventListener('input', calculateTotals);
            discountValueInput.addEventListener('input', calculateTotals);

            // Initial calculation
            calculateTotals();
        });
    </script>
</body>
</html>