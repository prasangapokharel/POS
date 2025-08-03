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

$supplier = null;
$message = '';
$message_type = '';

if (isset($_GET['id'])) {
    $supplier_id = $_GET['id'];
    try {
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1");
        $stmt->bindParam(':supplier_id', $supplier_id);
        $stmt->execute();
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            $message = 'Supplier not found.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Error fetching supplier for edit: " . $e->getMessage());
    }
} else {
    $message = 'No supplier ID provided.';
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supplier) {
    $supplier_id = $_POST['supplier_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $company = $_POST['company'] ?? null;
    $email = $_POST['email'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $address = $_POST['address'] ?? null;
    $city = $_POST['city'] ?? null;
    $state = $_POST['state'] ?? null;
    $zip_code = $_POST['zip_code'] ?? null;
    $country = $_POST['country'] ?? 'USA';
    $contact_person = $_POST['contact_person'] ?? null;
    $tax_id = $_POST['tax_id'] ?? null;
    $payment_terms = $_POST['payment_terms'] ?? null;
    $status = $_POST['status'] ?? 'active';

    try {
        $stmt = $db->prepare("UPDATE suppliers SET name = :name, company = :company, email = :email, phone = :phone, address = :address, city = :city, state = :state, zip_code = :zip_code, country = :country, contact_person = :contact_person, tax_id = :tax_id, payment_terms = :payment_terms, status = :status WHERE supplier_id = :supplier_id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':company', $company);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':state', $state);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':country', $country);
        $stmt->bindParam(':contact_person', $contact_person);
        $stmt->bindParam(':tax_id', $tax_id);
        $stmt->bindParam(':payment_terms', $payment_terms);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':supplier_id', $supplier_id);

        if ($stmt->execute()) {
            $message = 'Supplier updated successfully!';
            $message_type = 'success';
            // Re-fetch supplier data to show updated info
            $stmt = $db->prepare("SELECT * FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1");
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->execute();
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = 'Failed to update supplier.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Error updating supplier: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier - Billing System</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Supplier</h1>

            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($supplier): ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <form action="edit-supplier.php?id=<?php echo htmlspecialchars($supplier['supplier_id']); ?>" method="POST">
                        <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Supplier Name <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($supplier['name']); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="company" class="block text-gray-700 text-sm font-bold mb-2">Company Name</label>
                                <input type="text" id="company" name="company"
                                       value="<?php echo htmlspecialchars($supplier['company'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                                <input type="text" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                            <textarea id="address" name="address" rows="3"
                                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="city" class="block text-gray-700 text-sm font-bold mb-2">City</label>
                                <input type="text" id="city" name="city"
                                       value="<?php echo htmlspecialchars($supplier['city'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="state" class="block text-gray-700 text-sm font-bold mb-2">State</label>
                                <input type="text" id="state" name="state"
                                       value="<?php echo htmlspecialchars($supplier['state'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="zip_code" class="block text-gray-700 text-sm font-bold mb-2">Zip Code</label>
                                <input type="text" id="zip_code" name="zip_code"
                                       value="<?php echo htmlspecialchars($supplier['zip_code'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="country" class="block text-gray-700 text-sm font-bold mb-2">Country</label>
                            <input type="text" id="country" name="country"
                                   value="<?php echo htmlspecialchars($supplier['country'] ?? ''); ?>"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="contact_person" class="block text-gray-700 text-sm font-bold mb-2">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person"
                                       value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="tax_id" class="block text-gray-700 text-sm font-bold mb-2">Tax ID</label>
                                <input type="text" id="tax_id" name="tax_id"
                                       value="<?php echo htmlspecialchars($supplier['tax_id'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="payment_terms" class="block text-gray-700 text-sm font-bold mb-2">Payment Terms</label>
                                <input type="text" id="payment_terms" name="payment_terms"
                                       value="<?php echo htmlspecialchars($supplier['payment_terms'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                                <select id="status" name="status"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                    <option value="active" <?php echo ($supplier['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($supplier['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Update Supplier
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif (!$message): ?>
                <p class="text-gray-600 text-center">Loading supplier data...</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
