-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2025 at 08:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `billing_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `admin_id`, `name`, `email`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System Administrator', 'admin@billing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-07-29 02:05:13', '2025-07-29 02:05:13');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'USA',
  `status` enum('active','inactive') DEFAULT 'active',
  `total_purchases` decimal(10,2) DEFAULT 0.00,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_id`, `name`, `email`, `phone`, `address`, `city`, `state`, `zip_code`, `country`, `status`, `total_purchases`, `total_paid`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'CUST001', 'John Doe', 'john@example.com', '555-0101', '123 Main St', 'New York', 'NY', '10001', 'USA', 'active', 2500.00, 2000.00, 500.00, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(2, 'CUST002', 'Jane Smith', 'jane@example.com', '555-0102', '456 Oak Ave', 'Los Angeles', 'CA', '90001', 'USA', 'active', 1800.00, 1800.00, 0.00, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(3, 'CUST003', 'Mike Johnson', 'mike@example.com', '555-0103', '789 Pine St', 'Chicago', 'IL', '60601', 'USA', 'active', 3200.00, 2800.00, 400.00, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(4, 'CUST004', 'Sarah Wilson', 'sarah@example.com', '555-0104', '321 Elm St', 'Houston', 'TX', '77001', 'USA', 'active', 1500.00, 1200.00, 300.00, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(5, 'CUST005', 'David Brown', 'david@example.com', '555-0105', '654 Maple Dr', 'Phoenix', 'AZ', '85001', 'USA', 'active', 13100.00, 2100.00, 0.00, '2025-07-29 02:05:13', '2025-08-03 17:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments`
--

CREATE TABLE `inventory_adjustments` (
  `id` int(11) NOT NULL,
  `adjustment_id` varchar(20) NOT NULL,
  `item_id` varchar(20) NOT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `adjustment_quantity` int(11) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `adjusted_by` varchar(50) DEFAULT NULL,
  `adjustment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 10,
  `maximum_stock` int(11) DEFAULT 1000,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `supplier_id` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','discontinued') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_id`, `name`, `description`, `category`, `unit`, `current_stock`, `minimum_stock`, `maximum_stock`, `unit_cost`, `selling_price`, `supplier_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ITM001', 'Office Chair', 'Ergonomic office chair with lumbar support', 'Furniture', 'pcs', 25, 5, 1000, 5500.00, 7500.00, NULL, 'active', '2025-08-01 17:19:29', '2025-08-01 17:19:29'),
(2, 'ITM002', 'Laptop Stand', 'Adjustable aluminum laptop stand', 'Electronics', 'pcs', 15, 3, 1000, 2200.00, 3200.00, NULL, 'active', '2025-08-01 17:19:29', '2025-08-01 17:19:29'),
(3, 'ITM003', 'Wireless Mouse', 'Bluetooth wireless mouse', 'Electronics', 'pcs', 50, 10, 1000, 800.00, 1200.00, NULL, 'active', '2025-08-01 17:19:29', '2025-08-01 17:19:29'),
(4, 'ITM004', 'Desk Lamp', 'LED desk lamp with adjustable brightness', 'Furniture', 'pcs', 25, 8, 1000, 1500.00, 2200.00, NULL, 'active', '2025-08-01 17:19:29', '2025-08-03 17:40:13'),
(5, 'ITM005', 'Notebook Set', 'A4 ruled notebooks pack of 5', 'Stationery', 'pack', 100, 20, 1000, 350.00, 500.00, NULL, 'active', '2025-08-01 17:19:29', '2025-08-01 17:19:29');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(20) NOT NULL,
  `item_id` varchar(20) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','return') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `reference_id` varchar(20) DEFAULT NULL,
  `reference_type` varchar(20) DEFAULT NULL,
  `supplier_id` varchar(20) DEFAULT NULL,
  `customer_id` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `transaction_id`, `item_id`, `transaction_type`, `quantity`, `unit_cost`, `total_cost`, `reference_id`, `reference_type`, `supplier_id`, `customer_id`, `notes`, `transaction_date`, `created_by`, `created_at`) VALUES
(1, 'TRN688F9EFD90F0B', 'ITM004', 'sale', 5, 1500.00, 11000.00, 'ORD688F9EFD8D94E', 'order', NULL, 'CUST005', NULL, '2025-08-03', 'System Administrator', '2025-08-03 17:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('mobilebanking','esawa','cash') DEFAULT NULL,
  `status` enum('paid','unpaid','due') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_id`, `customer_id`, `order_date`, `total_amount`, `vat_amount`, `grand_total`, `payment_method`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ORD688F9EFD8D94E', 'CUST005', '2025-08-03 17:40:13', 11000.00, 0.00, 11000.00, 'cash', 'paid', '', '2025-08-03 17:40:13', '2025-08-03 17:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `item_id` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_id`, `quantity`, `unit_price`, `subtotal`, `created_at`) VALUES
(1, 'ORD688F9EFD8D94E', 'ITM004', 5, 2200.00, 11000.00, '2025-08-03 17:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `admin_id`, `expires_at`, `created_at`) VALUES
('35rdfblk8ma8t29380t65p20d0', 1, '2025-07-31 08:56:21', '2025-07-30 12:41:21'),
('59r4l19rkm6ookgdq5cri25lpo', 1, '2025-08-04 12:59:00', '2025-08-03 16:44:00'),
('804r4lk76s24h37tqm3a00hifh', 1, '2025-08-02 13:38:33', '2025-08-01 17:23:34'),
('htvf34s7brlb1emuup62b2jv6k', 1, '2025-07-29 22:20:18', '2025-07-29 02:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `company` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'USA',
  `contact_person` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `total_purchases` decimal(12,2) DEFAULT 0.00,
  `total_paid` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(12,2) DEFAULT 0.00,
  `total_items` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_id`, `name`, `company`, `email`, `phone`, `address`, `city`, `state`, `zip_code`, `country`, `contact_person`, `tax_id`, `payment_terms`, `status`, `total_purchases`, `total_paid`, `balance`, `total_items`, `created_at`, `updated_at`) VALUES
(1, 'SUP001', 'ABC Electronics', 'ABC Electronics Inc.', 'contact@abcelectronics.com', '555-1001', '100 Tech Blvd', 'San Jose', 'CA', '95101', 'USA', 'Robert Chen', 'TAX123456', 'Net 30', 'active', 10500.00, 10500.00, 0.00, 35, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(2, 'SUP002', 'Global Parts Co', 'Global Parts Company', 'sales@globalparts.com', '555-1002', '200 Industrial Way', 'Detroit', 'MI', '48201', 'USA', 'Maria Garcia', 'TAX789012', 'Net 15', 'active', 3750.00, 3750.00, 0.00, 15, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(3, 'SUP003', 'Tech Solutions Ltd', 'Tech Solutions Limited', 'info@techsolutions.com', '555-1003', '300 Innovation Dr', 'Austin', 'TX', '73301', 'USA', 'James Wilson', 'TAX345678', 'Net 45', 'active', 10000.00, 8000.00, 2000.00, 2, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(4, 'SUP004', 'Quality Materials', 'Quality Materials Corp', 'orders@qualitymaterials.com', '555-1004', '400 Supply Chain St', 'Atlanta', 'GA', '30301', 'USA', 'Lisa Anderson', 'TAX901234', 'Net 30', 'active', 7500.00, 5000.00, 2500.00, 50, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(5, 'SUP005', 'Premier Goods', 'Premier Goods LLC', 'contact@premiergoods.com', '555-1005', '500 Commerce Ave', 'Seattle', 'WA', '98101', 'USA', 'Michael Davis', 'TAX567890', 'Net 60', 'active', 1000.00, 1000.00, 0.00, 200, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(6, 'SUP3387', 'kapil', 'kapal', 'kapillopchan@icloud.com', '9816308527', 'Khoisan', 'sasa', 'sasas', '12345', 'USA', '92829292', '12', 'Net 30', 'active', 0.00, 0.00, 0.00, 0, '2025-07-29 02:06:02', '2025-07-29 02:06:02');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `payment_id` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','other') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('completed','pending','failed') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `payment_id`, `supplier_id`, `purchase_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `notes`, `status`, `created_at`) VALUES
(1, 'PAY001', 1, 1, 8000.00, '2024-01-25', 'bank_transfer', 'TXN123456', NULL, 'completed', '2025-07-29 02:05:13'),
(2, 'PAY002', 1, 2, 2500.00, '2024-01-28', 'bank_transfer', 'TXN123457', NULL, 'completed', '2025-07-29 02:05:13'),
(3, 'PAY003', 2, 3, 3750.00, '2024-01-30', 'check', 'CHK001', NULL, 'completed', '2025-07-29 02:05:13'),
(4, 'PAY004', 3, 4, 8000.00, '2024-02-05', 'bank_transfer', 'TXN123458', NULL, 'completed', '2025-07-29 02:05:13'),
(5, 'PAY005', 4, 5, 5000.00, '2024-02-08', 'bank_transfer', 'TXN123459', NULL, 'completed', '2025-07-29 02:05:13'),
(6, 'PAY006', 5, 6, 1000.00, '2024-02-10', 'credit_card', 'CC123456', NULL, 'completed', '2025-07-29 02:05:13');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_purchases`
--

CREATE TABLE `supplier_purchases` (
  `id` int(11) NOT NULL,
  `purchase_id` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('ordered','delivered','cancelled') DEFAULT 'ordered',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_purchases`
--

INSERT INTO `supplier_purchases` (`id`, `purchase_id`, `supplier_id`, `item_name`, `item_description`, `quantity`, `unit_cost`, `total_cost`, `purchase_date`, `delivery_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PUR001', 1, 'Laptop Computers', 'Dell Latitude 5520 Business Laptops', 10, 800.00, 8000.00, '2024-01-15', '2024-01-20', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(2, 'PUR002', 1, 'Wireless Mice', 'Logitech MX Master 3 Wireless Mouse', 25, 100.00, 2500.00, '2024-01-18', '2024-01-22', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(3, 'PUR003', 2, 'Office Chairs', 'Ergonomic Office Chairs with Lumbar Support', 15, 250.00, 3750.00, '2024-01-20', '2024-01-25', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(4, 'PUR004', 3, 'Server Hardware', 'Dell PowerEdge R740 Server', 2, 5000.00, 10000.00, '2024-01-22', '2024-01-30', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(5, 'PUR005', 4, 'Raw Materials', 'Steel Sheets 4x8 feet', 50, 150.00, 7500.00, '2024-01-25', '2024-02-01', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13'),
(6, 'PUR006', 5, 'Packaging Supplies', 'Cardboard Boxes Various Sizes', 200, 5.00, 1000.00, '2024-01-28', '2024-02-02', 'delivered', NULL, '2025-07-29 02:05:13', '2025-07-29 02:05:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_id` (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`),
  ADD KEY `idx_customers_customer_id` (`customer_id`),
  ADD KEY `idx_customers_status` (`status`);

--
-- Indexes for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adjustment_id` (`adjustment_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_id` (`item_id`),
  ADD KEY `idx_inventory_items_category` (`category`),
  ADD KEY `idx_inventory_items_status` (`status`),
  ADD KEY `idx_inventory_items_supplier` (`supplier_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_inventory_transactions_item` (`item_id`),
  ADD KEY `idx_inventory_transactions_type` (`transaction_type`),
  ADD KEY `idx_inventory_transactions_date` (`transaction_date`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_sessions_expires` (`expires_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_suppliers_supplier_id` (`supplier_id`),
  ADD KEY `idx_suppliers_status` (`status`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `idx_payments_supplier_id` (`supplier_id`),
  ADD KEY `idx_payments_date` (`payment_date`);

--
-- Indexes for table `supplier_purchases`
--
ALTER TABLE `supplier_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_id` (`purchase_id`),
  ADD KEY `idx_purchases_supplier_id` (`supplier_id`),
  ADD KEY `idx_purchases_date` (`purchase_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_purchases`
--
ALTER TABLE `supplier_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD CONSTRAINT `inventory_adjustments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_payments_ibfk_2` FOREIGN KEY (`purchase_id`) REFERENCES `supplier_purchases` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_purchases`
--
ALTER TABLE `supplier_purchases`
  ADD CONSTRAINT `supplier_purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
