-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 02:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `electroserve_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_insights`
--

CREATE TABLE `ai_insights` (
  `id` int(11) NOT NULL,
  `type` enum('revenue_trend','top_category','expense_analysis','stock_alert','sales_prediction','performance_analysis') DEFAULT 'revenue_trend',
  `title` varchar(255) NOT NULL,
  `insight_text` text NOT NULL,
  `insight_value` decimal(10,2) DEFAULT NULL,
  `insight_percent` decimal(5,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'Electrical Supplies', 'Cables, switches, breakers, wiring accessories', 'active', '2026-04-28 10:00:38'),
(2, 'Devices', 'Appliances, smart devices, equipment', 'active', '2026-04-28 10:00:38'),
(3, 'Plumbing Materials', 'Pipes, fittings, valves', 'active', '2026-04-28 10:00:38'),
(4, 'Tools & Accessories', 'Hand tools, power tools, accessories', 'active', '2026-04-28 10:00:38'),
(5, 'Safety Equipment', 'PPE, helmets, gloves, vests', 'active', '2026-04-28 10:00:38');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(20) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `full_name`, `email`, `phone`, `address`, `city`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(17, 'C-9602', 'NIYITANGA Eric', 'niyitangaeric77@gmail.com', '+250784474283', 'None', '', '', 'active', '2026-05-06 07:14:47', '2026-05-06 07:14:47'),
(18, 'C-1463', 'Gael', 'niyitangaeric77@gmail.com', '+250723978377', 'KN77', '', '', 'active', '2026-05-06 10:26:38', '2026-05-06 10:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(30) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `agreement_details` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `terms_and_conditions` text DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','active','completed','terminated','archived') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'other',
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque') DEFAULT 'cash',
  `technician_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_id` int(11) DEFAULT NULL COMMENT 'Reference to item if expense is for inventory',
  `quantity_purchased` int(11) DEFAULT NULL COMMENT 'Qty if this is inventory expense',
  `unit_cost` decimal(12,2) DEFAULT NULL COMMENT 'Cost per unit for inventory expenses',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'Reference to supplier if applicable',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_tracking`
--

CREATE TABLE `financial_tracking` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `revenue_type` enum('service','material','labor') DEFAULT 'service',
  `gross_amount` decimal(12,2) NOT NULL,
  `cost_base` decimal(12,2) DEFAULT 0.00 COMMENT 'Base cost (item cost price)',
  `profit_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Gross - Cost',
  `profit_percent` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `net_profit` decimal(12,2) DEFAULT 0.00 COMMENT 'Profit - Tax',
  `recorded_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(30) DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(12,2) DEFAULT 0.00,
  `type` enum('invoice','quotation','receipt') DEFAULT 'invoice',
  `status` enum('unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `ticket_id`, `client_id`, `subtotal`, `tax_percent`, `tax_amount`, `discount`, `total_amount`, `paid_amount`, `balance`, `type`, `status`, `due_date`, `notes`, `created_by`, `created_at`) VALUES
(20, 'INV-0037-20260506', 37, 17, 238615.67, 0.00, 0.00, 0.00, 238615.67, 238615.67, 0.00, 'invoice', 'paid', '2026-05-06', NULL, 1, '2026-05-06 07:18:25'),
(21, 'INV-0038-20260506', 38, 17, 192365.24, 0.00, 0.00, 0.00, 192365.24, 192365.24, 0.00, 'invoice', 'paid', '2026-05-06', NULL, 1, '2026-05-06 10:23:43'),
(22, 'INV-0039-20260506', 39, 18, 1565422.42, 0.00, 0.00, 0.00, 1565422.42, 1565422.42, 0.00, 'invoice', 'paid', '2026-05-06', NULL, 1, '2026-05-06 10:47:46'),
(23, 'INV-0040-20260506', 40, 18, 265759.60, 0.00, 0.00, 0.00, 265759.60, 265759.60, 0.00, 'invoice', 'paid', '2026-05-06', NULL, 1, '2026-05-06 10:58:46');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(30) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `unit` varchar(30) DEFAULT 'piece',
  `cost_price` decimal(12,2) DEFAULT 0.00,
  `selling_price` decimal(12,2) DEFAULT 0.00,
  `quantity` int(11) DEFAULT 0,
  `min_quantity` int(11) DEFAULT 5,
  `description` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_cost` decimal(12,2) DEFAULT 0.00 COMMENT 'Total cost including all expenses',
  `last_supplier_id` int(11) DEFAULT NULL COMMENT 'Last supplier this item was purchased from',
  `supplier_required` tinyint(4) DEFAULT 1 COMMENT '1=supplier must be selected, 0=optional'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `item_code`, `name`, `category_id`, `sub_category_id`, `unit`, `cost_price`, `selling_price`, `quantity`, `min_quantity`, `description`, `supplier_id`, `status`, `created_at`, `updated_at`, `total_cost`, `last_supplier_id`, `supplier_required`) VALUES
(32, 'ITM-0001', 'Generator #1', 2, 6, 'piece', 12000.00, 15600.00, 0, 5, NULL, NULL, 'active', '2026-05-06 07:17:42', '2026-05-06 10:57:49', 0.00, NULL, 1),
(33, 'ITM-0002', 'JIK', 3, 9, 'piece', 1000.00, 1200.00, 0, 5, NULL, NULL, 'active', '2026-05-06 10:35:21', '2026-05-06 10:57:58', 0.00, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `item_expenses`
--

CREATE TABLE `item_expenses` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `purchase_date` datetime DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `message_type` enum('text','file','system') DEFAULT 'text',
  `message` text NOT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `ticket_id`, `client_id`, `message_type`, `message`, `attachment_url`, `is_read`, `created_at`) VALUES
(3, 1, 16, NULL, NULL, 'text', 'hi', NULL, 0, '2026-05-05 16:09:36'),
(4, 1, 8, NULL, NULL, 'text', 'hi there', NULL, 1, '2026-05-06 11:14:51');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type` enum('ticket','stock','payment','message','system','contract','performance') DEFAULT 'system',
  `title` varchar(200) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `staff_id`, `client_id`, `type`, `title`, `body`, `is_read`, `read_at`, `action_url`, `priority`, `created_at`) VALUES
(52, 1, NULL, 'ticket', 'New ticket TK-0001 created', 'Ticket for service: Electrical installation', 1, NULL, NULL, 'medium', '2026-05-06 07:15:46'),
(53, 1, NULL, 'ticket', 'Ticket Alert', 'Ticket #TK-0001 materials confirmed. Ready to start service.', 1, NULL, NULL, 'medium', '2026-05-06 07:18:03'),
(54, 1, NULL, 'ticket', 'New ticket TK-0002 created', 'Ticket for service: jlk;klj;', 1, NULL, NULL, 'medium', '2026-05-06 10:21:09'),
(55, 1, NULL, 'ticket', 'Ticket Alert', 'Ticket #TK-0002 materials confirmed. Ready to start service.', 1, NULL, NULL, 'medium', '2026-05-06 10:22:32'),
(56, 1, NULL, 'ticket', 'New ticket TK-0003 created', 'Ticket for service: Robinet', 1, NULL, NULL, 'medium', '2026-05-06 10:27:53'),
(57, 1, NULL, 'ticket', 'Ticket Alert', 'Ticket #TK-0003 materials confirmed. Ready to start service.', 1, NULL, NULL, 'medium', '2026-05-06 10:31:05'),
(58, 1, NULL, 'ticket', 'New ticket TK-0004 created', 'Ticket for service: hiljhklj', 1, NULL, NULL, 'medium', '2026-05-06 10:57:28'),
(59, 1, NULL, 'ticket', 'Ticket Alert', 'Ticket #TK-0004 materials confirmed. Ready to start service.', 1, NULL, NULL, 'medium', '2026-05-06 10:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `profit_margin_history`
--

CREATE TABLE `profit_margin_history` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `old_margin` decimal(5,2) DEFAULT NULL,
  `new_margin` decimal(5,2) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(30) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','received','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `base_rate` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_types`
--

INSERT INTO `service_types` (`id`, `name`, `description`, `base_rate`, `status`) VALUES
(1, 'Electrical Installation', 'New wiring, panel setup, socket installation', 5000.00, 'active'),
(2, 'Device Repair', 'Repair of electrical appliances and devices', 8000.00, 'active'),
(3, 'Plumbing Service', 'Pipe installation, leak repair, fitting', 7000.00, 'active'),
(4, 'Device Supply', 'Supply and delivery of electrical devices', 6000.00, 'active'),
(5, 'Electrical Maintenance', 'Routine checkups and maintenance', 12000.00, 'active'),
(6, 'Emergency Service', '24/7 emergency electrical/plumbing calls', 10000.00, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_code` varchar(20) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','logistics','sales','technician','finance') DEFAULT 'sales',
  `department` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_code`, `full_name`, `email`, `phone`, `password`, `role`, `department`, `avatar`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ADM-001', 'KAYIRANGA Gael', 'admin@nusu.rw', '0784474283', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Management', NULL, 'active', '2026-04-28 10:00:38', '2026-04-29 10:59:56'),
(6, 'SLS-001', 'Irakoze Aline', 'aline@nusu.rw', '+1-555-0301', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sales', 'Sales', NULL, 'active', '2026-04-28 10:00:39', '2026-04-29 11:03:37'),
(7, 'FIN-001', 'Ndayishimiye Elisa', 'elisa@nusu.rw', '+1-555-0401', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance', 'Finance', NULL, 'active', '2026-04-28 10:00:39', '2026-04-29 11:04:41'),
(8, 'LOG-001', 'Lisa Cyusa', 'lisa@nusu.rw', '+1-555-0501', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'logistics', 'Warehouse', NULL, 'active', '2026-04-28 10:00:39', '2026-04-29 11:00:26'),
(11, 'TCH-347', 'John Doe', 'johndoe@nusu.rw', '0784474283', '$2y$10$WlrxYJjIDWTmgUHt9jCNPesX2C/yYhyaE99nx6cqKouHU47lSxj0S', 'technician', 'Field Operations', NULL, 'active', '2026-04-29 11:07:43', '2026-04-29 11:07:43'),
(12, 'TCH-495', 'Fred', 'Fred@gmail.com', '0784474283', '$2y$10$YP5NhX7CRysaZXNZ.KpIou8RUIHFmQkMIMm2aVvaCo7iJnL.hTPx2', 'technician', 'Field Operations', NULL, 'active', '2026-04-29 12:58:03', '2026-04-29 12:58:03'),
(13, 'TCH-234', 'John Doe', 'niyitangaeric222@gmail.com', '0784474283', '$2y$10$us6AnTpIE6TlyNHQwKTrmOad4Xs0WvqW/MZTcyicUFuHZnkHZ8t3a', 'technician', 'Field Operations', NULL, 'active', '2026-04-29 15:43:33', '2026-04-29 15:43:33'),
(16, 'TCH-572', 'Technician', 'technician@gmail.com', '0784474283', '$2y$10$.Ws68SAvBd8f1D9R78Ufr.TVOWOMzzJk8FMVIvZU48giRLNx4RvaS', 'technician', 'Field Operations', NULL, 'active', '2026-04-30 08:38:50', '2026-04-30 08:38:50'),
(18, 'TCH-243', 'John Doe', 'johndoe@nussu.rw', '0784474283', '$2y$10$zDKl6uN.sBSnVRxy.k4V8.BIDB3vHqT0JrIAdsGjoPSMp2xRdYHGG', 'technician', 'Field Operations', NULL, 'active', '2026-05-03 10:24:13', '2026-05-03 10:24:13'),
(20, 'TCH-436', 'Eric Niyitanga', 'niyitangaeric77@gmail.com', '0723978377', '$2y$10$B2XJPtFF7NfObYrscw7y9.G6n5W8U1yl/2pLbemiTMB8lcqJqj6Gy', 'technician', 'Field Operations', NULL, 'active', '2026-05-04 06:15:16', '2026-05-04 06:15:16'),
(21, 'TCH-522', 'John Doe', 'niyitangaeric@gmail.com', '0784474283', '$2y$10$OghU0pzBU7pctJxGIbQpDuiwK7M/ebvWObdUfph5Lkkj.CUmFL1xO', 'technician', 'Field Operations', NULL, 'active', '2026-05-06 07:16:29', '2026-05-06 07:16:29');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment','ticket_used') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','confirmed','reversed') DEFAULT 'confirmed' COMMENT 'Track movement status',
  `cost_per_unit` decimal(12,2) DEFAULT 0.00 COMMENT 'Cost price used at time of movement',
  `selling_price_used` decimal(12,2) DEFAULT 0.00 COMMENT 'Selling price used for invoice',
  `profit_margin_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'Profit margin applied',
  `ticket_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `type`, `quantity`, `reference`, `notes`, `staff_id`, `created_at`, `status`, `cost_per_unit`, `selling_price_used`, `profit_margin_percent`, `ticket_id`) VALUES
(45, 32, 'in', 119, NULL, 'Initial stock', 1, '2026-05-06 07:17:42', 'confirmed', 0.00, 0.00, 0.00, NULL),
(46, 32, 'ticket_used', 12, 'TK-0001', 'Immediate deduction on ticket add', 1, '2026-05-06 07:17:59', 'confirmed', 0.00, 0.00, 0.00, 37),
(47, 32, 'ticket_used', 10, 'TK-0002', 'Immediate deduction on ticket add', 1, '2026-05-06 10:22:17', 'confirmed', 0.00, 0.00, 0.00, 38),
(48, 32, 'ticket_used', 7, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:28:22', 'confirmed', 0.00, 0.00, 0.00, 39),
(49, 32, 'ticket_used', 4, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:28:51', 'confirmed', 0.00, 0.00, 0.00, 39),
(50, 32, 'ticket_used', 7, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:29:28', 'confirmed', 0.00, 0.00, 0.00, 39),
(51, 32, 'ticket_used', 45, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:29:41', 'confirmed', 0.00, 0.00, 0.00, 39),
(52, 32, 'ticket_used', 12, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:30:00', 'confirmed', 0.00, 0.00, 0.00, 39),
(53, 32, 'ticket_used', 3, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:30:28', 'confirmed', 0.00, 0.00, 0.00, 39),
(54, 32, 'ticket_used', 4, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:30:42', 'confirmed', 0.00, 0.00, 0.00, 39),
(55, 32, 'ticket_used', 2, 'TK-0003', 'Immediate deduction on ticket add', 1, '2026-05-06 10:30:54', 'confirmed', 0.00, 0.00, 0.00, 39),
(56, 33, 'in', 12, NULL, 'Initial stock', 1, '2026-05-06 10:35:21', 'confirmed', 0.00, 0.00, 0.00, NULL),
(57, 32, 'ticket_used', 13, 'TK-0004', 'Immediate deduction on ticket add', 1, '2026-05-06 10:57:49', 'confirmed', 0.00, 0.00, 0.00, 40),
(58, 33, 'ticket_used', 12, 'TK-0004', 'Immediate deduction on ticket add', 1, '2026-05-06 10:57:58', 'confirmed', 0.00, 0.00, 0.00, 40);

-- --------------------------------------------------------

--
-- Table structure for table `sub_categories`
--

CREATE TABLE `sub_categories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `profit_margin` decimal(5,2) DEFAULT 20.00 COMMENT 'Profit margin percentage - inherited by all items',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_categories`
--

INSERT INTO `sub_categories` (`id`, `category_id`, `name`, `description`, `profit_margin`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Cables & Wires', NULL, 20.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(2, 1, 'Switches', NULL, 15.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(3, 1, 'Circuit Breakers', NULL, 18.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(4, 1, 'Conduits', NULL, 20.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(5, 2, 'Air Conditioners', NULL, 25.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(6, 2, 'Generators', NULL, 30.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(7, 2, 'Smart Meters', NULL, 22.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(8, 3, 'PVC Pipes', NULL, 18.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(9, 3, 'Ball Valves', NULL, 20.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(10, 3, 'Pipe Fittings', NULL, 19.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(11, 4, 'Hand Tools', NULL, 25.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(12, 4, 'Power Tools', NULL, 28.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(13, 4, 'Measuring Tools', NULL, 22.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(14, 5, 'Helmets', NULL, 20.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(15, 5, 'Gloves', NULL, 15.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(16, 5, 'Safety Vests', NULL, 18.00, 'active', '2026-04-28 10:00:38', '2026-04-28 10:00:38'),
(17, 2, 'mol;mom', '', 20.00, 'inactive', '2026-04-29 10:49:02', '2026-04-29 10:49:10'),
(18, 1, 'Diogene MUSABE', '', 700.00, 'inactive', '2026-04-29 19:01:43', '2026-04-29 19:03:04'),
(19, 2, 'My subcategory', 'here is the description 👍👍👍👍', 30.00, 'active', '2026-04-30 08:39:38', '2026-04-30 08:39:38');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(20) DEFAULT NULL,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `taxes`
--

CREATE TABLE `taxes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `taxes`
--

INSERT INTO `taxes` (`id`, `name`, `rate`, `status`, `created_at`) VALUES
(1, 'VAT', 18.00, 'active', '2026-04-28 10:00:38');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 5.00,
  `total_jobs` int(11) DEFAULT 0,
  `status` enum('active','on_leave','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`id`, `staff_id`, `specialization`, `rating`, `total_jobs`, `status`) VALUES
(13, 21, 'Plumbing &amp; Electrical', 5.00, 0, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `service_type_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','assigned','confirmed','ongoing','completed','closed','denied') DEFAULT 'pending',
  `technician_id` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `location` text DEFAULT NULL,
  `service_cost` decimal(12,2) DEFAULT 0.00,
  `material_cost` decimal(12,2) DEFAULT 0.00,
  `labor_cost` decimal(12,2) DEFAULT 0.00,
  `profit_percent` decimal(5,2) DEFAULT 20.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `denial_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `loading_ticket` tinyint(4) DEFAULT 0 COMMENT '1=ticket confirmed and loading, 0=not',
  `confirmed_by_staff_id` int(11) DEFAULT NULL COMMENT 'Staff member who confirmed materials with client',
  `client_cost_confirmed` tinyint(4) DEFAULT 0 COMMENT '1=client confirmed cost, 0=not',
  `cost_confirmation_date` datetime DEFAULT NULL COMMENT 'When client confirmed cost',
  `material_confirmed` tinyint(4) DEFAULT 0,
  `time_total_minutes` decimal(10,2) DEFAULT 0.00,
  `time_start` datetime DEFAULT NULL,
  `time_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_number`, `client_id`, `service_type_id`, `title`, `description`, `priority`, `status`, `technician_id`, `assigned_at`, `confirmed_at`, `started_at`, `completed_at`, `closed_at`, `location`, `service_cost`, `material_cost`, `labor_cost`, `profit_percent`, `total_amount`, `notes`, `denial_reason`, `created_by`, `created_at`, `updated_at`, `loading_ticket`, `confirmed_by_staff_id`, `client_cost_confirmed`, `cost_confirmation_date`, `material_confirmed`, `time_total_minutes`, `time_start`, `time_end`) VALUES
(37, 'TK-0001', 17, 1, 'Electrical installation', 'description here 😒😒😒', 'urgent', 'closed', 13, '2026-05-06 09:16:59', '2026-05-06 09:18:03', '2026-05-06 09:18:08', '2026-05-06 09:18:20', '2026-05-06 09:18:31', 'kayonza/kabarondo/cyabajwa', 15000.00, 187200.00, 16.67, 20.00, 238615.67, NULL, NULL, 1, '2026-05-06 07:15:46', '2026-05-06 07:18:31', 0, NULL, 0, NULL, 1, 12.00, NULL, '2026-05-06 09:18:20'),
(38, 'TK-0002', 17, 3, 'jlk;klj;', '', 'medium', 'closed', 13, '2026-05-06 12:21:45', '2026-05-06 12:22:32', '2026-05-06 12:22:52', '2026-05-06 12:23:04', '2026-05-06 12:25:22', '', 7000.00, 156000.00, 21.39, 20.00, 192365.24, NULL, NULL, 1, '2026-05-06 10:21:09', '2026-05-06 10:25:22', 0, NULL, 0, NULL, 1, 11.00, NULL, '2026-05-06 12:23:04'),
(39, 'TK-0003', 18, 1, 'Robinet', 'Replacment of a default water tap', 'high', 'closed', 13, '2026-05-06 12:27:53', '2026-05-06 12:31:05', '2026-05-06 12:31:33', '2026-05-06 12:47:02', '2026-05-06 12:56:32', 'Vision City', 15000.00, 1310400.00, 1229.17, 20.00, 1565422.42, NULL, NULL, 1, '2026-05-06 10:27:53', '2026-05-06 10:56:32', 0, NULL, 0, NULL, 1, 885.00, NULL, '2026-05-06 12:47:02'),
(40, 'TK-0004', 18, 2, 'hiljhklj', '', 'medium', 'closed', 13, '2026-05-06 12:57:28', '2026-05-06 12:58:04', '2026-05-06 12:58:10', '2026-05-06 12:58:20', '2026-05-06 12:59:44', '', 8000.00, 217200.00, 20.00, 20.00, 265759.60, NULL, NULL, 1, '2026-05-06 10:57:28', '2026-05-06 10:59:44', 0, NULL, 0, NULL, 1, 9.00, NULL, '2026-05-06 12:58:20');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_confirmations`
--

CREATE TABLE `ticket_confirmations` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `confirmation_type` enum('material','cost','client_cost','payment') NOT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmation_date` datetime DEFAULT current_timestamp(),
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional confirmation details' CHECK (json_valid(`details`)),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_denial_log`
--

CREATE TABLE `ticket_denial_log` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `denied_by` int(11) DEFAULT NULL,
  `denial_date` datetime DEFAULT current_timestamp(),
  `denial_reason` text NOT NULL,
  `denial_category` enum('no_materials','cost_issue','no_technician','client_unavailable','other') DEFAULT 'other',
  `can_reopen` tinyint(4) DEFAULT 1,
  `reopened_by` int(11) DEFAULT NULL,
  `reopen_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_items`
--

CREATE TABLE `ticket_items` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(200) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_items`
--

INSERT INTO `ticket_items` (`id`, `ticket_id`, `item_id`, `item_name`, `quantity`, `unit_price`, `total_price`) VALUES
(39, 37, 32, 'Generator #1', 12, 15600.00, 187200.00),
(40, 38, 32, 'Generator #1', 10, 15600.00, 156000.00),
(41, 39, 32, 'Generator #1', 7, 15600.00, 109200.00),
(42, 39, 32, 'Generator #1', 4, 15600.00, 62400.00),
(43, 39, 32, 'Generator #1', 7, 15600.00, 109200.00),
(44, 39, 32, 'Generator #1', 45, 15600.00, 702000.00),
(45, 39, 32, 'Generator #1', 12, 15600.00, 187200.00),
(46, 39, 32, 'Generator #1', 3, 15600.00, 46800.00),
(47, 39, 32, 'Generator #1', 4, 15600.00, 62400.00),
(48, 39, 32, 'Generator #1', 2, 15600.00, 31200.00),
(49, 40, 32, 'Generator #1', 13, 15600.00, 202800.00),
(50, 40, 33, 'JIK', 12, 1200.00, 14400.00);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_logs`
--

INSERT INTO `ticket_logs` (`id`, `ticket_id`, `status`, `notes`, `staff_id`, `created_at`) VALUES
(128, 37, 'pending', 'Ticket created', 1, '2026-05-06 07:15:46'),
(129, 37, 'assigned', 'Technician assigned', 1, '2026-05-06 07:16:59'),
(130, 37, 'confirmed', 'Materials confirmed by technician. Cost approved: 202200', 1, '2026-05-06 07:18:03'),
(131, 37, 'ongoing', 'Service started - timer running', 1, '2026-05-06 07:18:08'),
(132, 37, 'completed', 'Service complete. Time: 00:00:12', 1, '2026-05-06 07:18:20'),
(133, 37, 'completed', 'Partial payment FRW 202,217. Remaining: FRW 36,399', 1, '2026-05-06 07:18:25'),
(134, 37, 'closed', 'Ticket closed after full payment of FRW 36,399', 1, '2026-05-06 07:18:31'),
(135, 38, 'pending', 'Ticket created', 1, '2026-05-06 10:21:09'),
(136, 38, 'assigned', 'Technician assigned', 1, '2026-05-06 10:21:45'),
(137, 38, 'confirmed', 'Materials confirmed by technician. Cost approved: 163000', 1, '2026-05-06 10:22:32'),
(138, 38, 'ongoing', 'Service started - timer running', 1, '2026-05-06 10:22:52'),
(139, 38, 'completed', 'Service complete. Time: 00:00:11', 1, '2026-05-06 10:23:04'),
(140, 38, 'completed', 'Partial payment FRW 163,021. Remaining: FRW 29,344', 1, '2026-05-06 10:23:43'),
(141, 38, 'closed', 'Ticket closed after full payment of FRW 29,344', 1, '2026-05-06 10:25:22'),
(142, 39, 'pending', 'Ticket created', 1, '2026-05-06 10:27:53'),
(143, 39, 'confirmed', 'Materials confirmed by technician. Cost approved: 1325400', 1, '2026-05-06 10:31:05'),
(144, 39, 'ongoing', 'Service started - timer running', 1, '2026-05-06 10:31:33'),
(145, 39, 'completed', 'Service complete. Time: 00:14:45', 1, '2026-05-06 10:47:02'),
(146, 39, 'completed', 'Partial payment FRW 1,000,000. Remaining: FRW 565,422', 1, '2026-05-06 10:47:46'),
(147, 39, 'closed', 'Ticket closed after full payment of FRW 565,422', 1, '2026-05-06 10:56:32'),
(148, 40, 'pending', 'Ticket created', 1, '2026-05-06 10:57:28'),
(149, 40, 'confirmed', 'Materials confirmed by technician. Cost approved: 225200', 1, '2026-05-06 10:58:04'),
(150, 40, 'ongoing', 'Service started - timer running', 1, '2026-05-06 10:58:10'),
(151, 40, 'completed', 'Service complete. Time: 00:00:09', 1, '2026-05-06 10:58:20'),
(152, 40, 'completed', 'Partial payment FRW 50,000. Remaining: FRW 215,760', 1, '2026-05-06 10:58:46'),
(153, 40, 'completed', 'Partial payment FRW 80,000. Remaining: FRW 135,760', 1, '2026-05-06 10:59:02'),
(154, 40, 'completed', 'Partial payment FRW 40,000. Remaining: FRW 95,760', 1, '2026-05-06 10:59:36'),
(155, 40, 'closed', 'Ticket closed after full payment of FRW 95,760', 1, '2026-05-06 10:59:44');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_operations`
--

CREATE TABLE `ticket_operations` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL COMMENT 'Status this operation belongs to',
  `operation_type` enum('assign_tech','add_material','confirm_material','client_confirm_cost','start_timer','stop_timer','process_payment') NOT NULL,
  `operation_name` varchar(255) DEFAULT NULL,
  `is_required` tinyint(4) DEFAULT 1 COMMENT '1=must complete, 0=optional',
  `is_completed` tinyint(4) DEFAULT 0 COMMENT '1=completed, 0=pending',
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_status_transitions`
--

CREATE TABLE `ticket_status_transitions` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `transitioned_by` int(11) DEFAULT NULL,
  `transition_date` datetime DEFAULT current_timestamp(),
  `allowed` tinyint(4) DEFAULT 1 COMMENT '1=allowed transition, 0=blocked',
  `block_reason` text DEFAULT NULL COMMENT 'Why transition was blocked if applicable',
  `transition_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional data for transition' CHECK (json_valid(`transition_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_time_logs`
--

CREATE TABLE `ticket_time_logs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `action` enum('start','stop','pause','resume') NOT NULL,
  `action_time` datetime DEFAULT current_timestamp(),
  `duration_seconds` int(11) DEFAULT 0 COMMENT 'Time spent in seconds',
  `performed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_time_logs`
--

INSERT INTO `ticket_time_logs` (`id`, `ticket_id`, `action`, `action_time`, `duration_seconds`, `performed_by`, `notes`) VALUES
(41, 37, 'start', '2026-05-06 09:18:08', 0, 1, NULL),
(42, 37, 'stop', '2026-05-06 09:18:20', 0, 1, 'Total service time: 00:00:12'),
(43, 38, 'start', '2026-05-06 12:22:52', 0, 1, NULL),
(44, 38, 'stop', '2026-05-06 12:23:04', 0, 1, 'Total service time: 00:00:11'),
(45, 39, 'start', '2026-05-06 12:31:33', 0, 1, NULL),
(46, 39, 'pause', '2026-05-06 12:46:18', 0, 1, 'Seconds worked: 885'),
(47, 39, 'stop', '2026-05-06 12:47:02', 0, 1, 'Total service time: 00:14:45'),
(48, 40, 'start', '2026-05-06 12:58:10', 0, 1, NULL),
(49, 40, 'stop', '2026-05-06 12:58:20', 0, 1, 'Total service time: 00:00:09');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `type` enum('income','expense','payment','refund') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `payment_method` enum('cash','card','transfer','cheque','mobile') DEFAULT 'cash',
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `reference`, `type`, `category`, `amount`, `invoice_id`, `description`, `payment_method`, `staff_id`, `created_at`) VALUES
(31, 'TXN-1E6DAC-37', 'income', 'service', 202216.67, 20, 'Payment for TK-0001', 'cash', 1, '2026-05-06 07:18:25'),
(32, 'TXN-779F3F-37', 'income', 'service', 36399.00, 20, 'Payment for TK-0001', 'cash', 1, '2026-05-06 07:18:31'),
(33, 'TXN-F73C66-38', 'income', 'service', 163021.39, 21, 'Payment for TK-0002', 'card', 1, '2026-05-06 10:23:43'),
(34, 'TXN-266956-38', 'income', 'service', 29343.85, 21, 'Payment for TK-0002', 'cash', 1, '2026-05-06 10:25:22'),
(35, 'TXN-274052-39', 'income', 'service', 1000000.00, 22, 'Payment for TK-0003', 'transfer', 1, '2026-05-06 10:47:46'),
(36, 'TXN-064658-39', 'income', 'service', 565422.42, 22, 'Payment for TK-0003', 'cash', 1, '2026-05-06 10:56:32'),
(37, 'TXN-65EFE4-40', 'income', 'service', 50000.00, 23, 'Payment for TK-0004', 'cash', 1, '2026-05-06 10:58:46'),
(38, 'TXN-62A3AF-40', 'income', 'service', 80000.00, 23, 'Payment for TK-0004', 'cash', 1, '2026-05-06 10:59:02'),
(39, 'TXN-8A8E36-40', 'income', 'service', 40000.00, 23, 'Payment for TK-0004', 'cash', 1, '2026-05-06 10:59:36'),
(40, 'TXN-07A08C-40', 'income', 'service', 95759.60, 23, 'Payment for TK-0004', 'cash', 1, '2026-05-06 10:59:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_technician` (`technician_id`),
  ADD KEY `fk_expense_item` (`item_id`),
  ADD KEY `fk_expense_supplier` (`supplier_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `financial_tracking`
--
ALTER TABLE `financial_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_date` (`recorded_date`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_invoices_ticket` (`ticket_id`),
  ADD KEY `idx_invoices_client` (`client_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `sub_category_id` (`sub_category_id`),
  ADD KEY `fk_items_supplier` (`last_supplier_id`);

--
-- Indexes for table `item_expenses`
--
ALTER TABLE `item_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_id` (`expense_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_item` (`item_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_purchase_date` (`purchase_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `profit_margin_history`
--
ALTER TABLE `profit_margin_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `sub_category_id` (`sub_category_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `staff_code` (`staff_code`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_stock_movements_item` (`item_id`),
  ADD KEY `idx_stock_movements_ticket` (`ticket_id`);

--
-- Indexes for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `taxes`
--
ALTER TABLE `taxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `service_type_id` (`service_type_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_tickets_status` (`status`),
  ADD KEY `idx_tickets_client` (`client_id`),
  ADD KEY `idx_tickets_technician` (`technician_id`),
  ADD KEY `idx_tickets_created` (`created_at`);

--
-- Indexes for table `ticket_confirmations`
--
ALTER TABLE `ticket_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `confirmed_by` (`confirmed_by`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_type` (`confirmation_type`);

--
-- Indexes for table `ticket_denial_log`
--
ALTER TABLE `ticket_denial_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `denied_by` (`denied_by`),
  ADD KEY `reopened_by` (`reopened_by`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_denied_date` (`denial_date`);

--
-- Indexes for table `ticket_items`
--
ALTER TABLE `ticket_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_ticket_items_ticket` (`ticket_id`);

--
-- Indexes for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `ticket_operations`
--
ALTER TABLE `ticket_operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `completed_by` (`completed_by`),
  ADD KEY `idx_ticket_status` (`ticket_id`,`status`),
  ADD KEY `idx_completed` (`is_completed`);

--
-- Indexes for table `ticket_status_transitions`
--
ALTER TABLE `ticket_status_transitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transitioned_by` (`transitioned_by`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_date` (`transition_date`);

--
-- Indexes for table `ticket_time_logs`
--
ALTER TABLE `ticket_time_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_action_time` (`action_time`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_insights`
--
ALTER TABLE `ai_insights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_tracking`
--
ALTER TABLE `financial_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `item_expenses`
--
ALTER TABLE `item_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `profit_margin_history`
--
ALTER TABLE `profit_margin_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `sub_categories`
--
ALTER TABLE `sub_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `taxes`
--
ALTER TABLE `taxes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `ticket_confirmations`
--
ALTER TABLE `ticket_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_denial_log`
--
ALTER TABLE `ticket_denial_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_items`
--
ALTER TABLE `ticket_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT for table `ticket_operations`
--
ALTER TABLE `ticket_operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_status_transitions`
--
ALTER TABLE `ticket_status_transitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_time_logs`
--
ALTER TABLE `ticket_time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contracts_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expense_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expense_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `financial_tracking`
--
ALTER TABLE `financial_tracking`
  ADD CONSTRAINT `financial_tracking_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `financial_tracking_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `financial_tracking_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_supplier` FOREIGN KEY (`last_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_expenses`
--
ALTER TABLE `item_expenses`
  ADD CONSTRAINT `item_expenses_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_expenses_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `item_expenses_ibfk_3` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `item_expenses_ibfk_4` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profit_margin_history`
--
ALTER TABLE `profit_margin_history`
  ADD CONSTRAINT `profit_margin_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `profit_margin_history_ibfk_2` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `profit_margin_history_ibfk_3` FOREIGN KEY (`changed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD CONSTRAINT `sub_categories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `technicians`
--
ALTER TABLE `technicians`
  ADD CONSTRAINT `technicians_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_confirmations`
--
ALTER TABLE `ticket_confirmations`
  ADD CONSTRAINT `ticket_confirmations_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_confirmations_ibfk_2` FOREIGN KEY (`confirmed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_denial_log`
--
ALTER TABLE `ticket_denial_log`
  ADD CONSTRAINT `ticket_denial_log_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_denial_log_ibfk_2` FOREIGN KEY (`denied_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ticket_denial_log_ibfk_3` FOREIGN KEY (`reopened_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_items`
--
ALTER TABLE `ticket_items`
  ADD CONSTRAINT `ticket_items_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD CONSTRAINT `ticket_logs_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_operations`
--
ALTER TABLE `ticket_operations`
  ADD CONSTRAINT `ticket_operations_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_operations_ibfk_2` FOREIGN KEY (`completed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_status_transitions`
--
ALTER TABLE `ticket_status_transitions`
  ADD CONSTRAINT `ticket_status_transitions_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_status_transitions_ibfk_2` FOREIGN KEY (`transitioned_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_time_logs`
--
ALTER TABLE `ticket_time_logs`
  ADD CONSTRAINT `ticket_time_logs_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_time_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
