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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? null;
    $category = $_POST['category'] ?? null;
    $unit = $_POST['unit'] ?? 'pcs';
    $current_stock = (int)($_POST['current_stock'] ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 10);
    $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0.00);
    $selling_price = (float)($_POST['selling_price'] ?? 0.00);
    $supplier_id = $_POST['supplier_id'] ?? null; // Assuming supplier_id is passed

    // Generate a simple unique item ID
    $item_id = 'ITM' . strtoupper(uniqid());

    try {
        $stmt = $db->prepare("INSERT INTO inventory_items (item_id, name, description, category, unit, current_stock, minimum_stock, maximum_stock, unit_cost, selling_price, supplier_id) VALUES (:item_id, :name, :description, :category, :unit, :current_stock, :minimum_stock, :maximum_stock, :unit_cost, :selling_price, :supplier_id)");
        $stmt->bindParam(':item_id', $item_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':unit', $unit);
        $stmt->bindParam(':current_stock', $current_stock, PDO::PARAM_INT);
        $stmt->bindParam(':minimum_stock', $minimum_stock, PDO::PARAM_INT);
        $stmt->bindParam(':maximum_stock', $maximum_stock, PDO::PARAM_INT);
        $stmt->bindParam(':unit_cost', $unit_cost);
        $stmt->bindParam(':selling_price', $selling_price);
        $stmt->bindParam(':supplier_id', $supplier_id);

        if ($stmt->execute()) {
            $message = 'Inventory item added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to add inventory item.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Error adding inventory item: " . $e->getMessage());
    }
}

// Fetch suppliers for dropdown
$suppliers = [];
try {
    $stmt = $db->query("SELECT supplier_id, name FROM suppliers ORDER BY name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory Item - Billing System</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Add New Inventory Item</h1>

            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <form action="add-item.php" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Item Name <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" required
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                        <div>
                            <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                            <input type="text" id="category" name="category"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label for="unit" class="block text-gray-700 text-sm font-bold mb-2">Unit</label>
                            <input type="text" id="unit" name="unit" value="pcs"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                        <div>
                            <label for="current_stock" class="block text-gray-700 text-sm font-bold mb-2">Current Stock <span class="text-red-500">*</span></label>
                            <input type="number" id="current_stock" name="current_stock" required value="0" min="0"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                        <div>
                            <label for="minimum_stock" class="block text-gray-700 text-sm font-bold mb-2">Minimum Stock</label>
                            <input type="number" id="minimum_stock" name="minimum_stock" value="10" min="0"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="unit_cost" class="block text-gray-700 text-sm font-bold mb-2">Unit Cost <span class="text-red-500">*</span></label>
                            <input type="number" id="unit_cost" name="unit_cost" required step="0.01" value="0.00" min="0"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                        <div>
                            <label for="selling_price" class="block text-gray-700 text-sm font-bold mb-2">Selling Price <span class="text-red-500">*</span></label>
                            <input type="number" id="selling_price" name="selling_price" required step="0.01" value="0.00" min="0"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="supplier_id" class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                        <select id="supplier_id" name="supplier_id"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            <option value="">Select Supplier (Optional)</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
