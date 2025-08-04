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
$admins = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                
                if (!empty($name) && !empty($email)) {
                    // Generate unique admin_id
                    $admin_id = 'ADM' . strtoupper(substr(uniqid(), -6));
                    
                    // Generate random password
                    $password = bin2hex(random_bytes(4)); // 8 character password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        // Check if email already exists
                        $check_stmt = $db->prepare("SELECT id FROM admin WHERE email = :email");
                        $check_stmt->bindParam(':email', $email);
                        $check_stmt->execute();
                        
                        if ($check_stmt->rowCount() > 0) {
                            $message = 'Email already exists!';
                            $message_type = 'error';
                        } else {
                            $stmt = $db->prepare("INSERT INTO admin (admin_id, name, email, password, status) VALUES (:admin_id, :name, :email, :password, 'active')");
                            $stmt->bindParam(':admin_id', $admin_id);
                            $stmt->bindParam(':name', $name);
                            $stmt->bindParam(':email', $email);
                            $stmt->bindParam(':password', $hashed_password);
                            
                            if ($stmt->execute()) {
                                $message = "User created successfully! Admin ID: {$admin_id}, Password: {$password}";
                                $message_type = 'success';
                            } else {
                                $message = 'Failed to create user.';
                                $message_type = 'error';
                            }
                        }
                    } catch (PDOException $e) {
                        $message = 'Database error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Error creating user: " . $e->getMessage());
                    }
                } else {
                    $message = 'Please fill in all required fields.';
                    $message_type = 'error';
                }
                break;
                
            case 'update_password':
                $user_id = $_POST['user_id'];
                $new_password = trim($_POST['new_password']);
                
                if (!empty($new_password) && !empty($user_id)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $db->prepare("UPDATE admin SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':id', $user_id);
                        
                        if ($stmt->execute()) {
                            $message = 'Password updated successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to update password.';
                            $message_type = 'error';
                        }
                    } catch (PDOException $e) {
                        $message = 'Database error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Error updating password: " . $e->getMessage());
                    }
                } else {
                    $message = 'Please provide a valid password.';
                    $message_type = 'error';
                }
                break;
                
            case 'generate_password':
                $user_id = $_POST['user_id'];
                
                if (!empty($user_id)) {
                    $new_password = bin2hex(random_bytes(4)); // 8 character password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $db->prepare("UPDATE admin SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':id', $user_id);
                        
                        if ($stmt->execute()) {
                            $message = "New password generated: {$new_password}";
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to generate new password.';
                            $message_type = 'error';
                        }
                    } catch (PDOException $e) {
                        $message = 'Database error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Error generating password: " . $e->getMessage());
                    }
                }
                break;
        }
    }
}

// Fetch all admin users
try {
    $stmt = $db->query("SELECT * FROM admin ORDER BY created_at DESC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error fetching admin users: ' . $e->getMessage();
    $message_type = 'error';
    error_log("Error fetching admins: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
<body class="bg-gray-100">
    <?php include __DIR__ . '/../component/navbar.php'; ?>
    
    <div class="flex">
        <?php include __DIR__ . '/../component/sidebar.php'; ?>
        
        <main class="flex-1">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Settings</h1>
                <p class="text-gray-600">Manage admin users and system settings</p>
            </div>

            <?php if ($message): ?>
                <div class="p-4 mb-6 text-sm rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>" role="alert">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Create New User -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-user-plus text-white"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Create New Admin User</h2>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div class="bg-accent/10 border border-accent/20 rounded-md p-3">
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-info-circle text-accent mr-1"></i>
                                Admin ID and password will be automatically generated
                            </p>
                        </div>
                        
                        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-md transition duration-200 flex items-center justify-center">
                            Create Admin User
                        </button>
                    </form>
                </div>

                <!-- Update Password -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-accent rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-key text-white"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Update Password</h2>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_password">
                        
                        <div>
                            <label for="user_select" class="block text-sm font-medium text-gray-700 mb-1">Select Admin User</label>
                            <select id="user_select" name="user_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
                                <option value="">Choose an admin user...</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo htmlspecialchars($admin['id']); ?>">
                                        <?php echo htmlspecialchars($admin['name'] . ' (' . $admin['admin_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
                        </div>
                        
                        <button type="submit" class="w-full bg-accent hover:bg-accent-dark text-white font-medium py-2 px-4 rounded-md transition duration-200 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i>
                            Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Admin Users List -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gray-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Admin Users</h2>
                    </div>
                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-medium">
                        <?php echo count($admins); ?> users
                    </span>
                </div>
                
                <?php if (empty($admins)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                        <p class="text-gray-500">No admin users found.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($admins as $admin): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-primary"><?php echo htmlspecialchars($admin['admin_id']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-white text-xs"></i>
                                                </div>
                                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($admin['email']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $admin['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($admin['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="generate_password">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($admin['id']); ?>">
                                                <button type="submit" onclick="return confirm('Generate new password for this user?');"
                                                        class="text-accent hover:text-accent-dark mr-3 transition duration-200">
                                                    <i class="fas fa-random mr-1"></i>Generate Password
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>