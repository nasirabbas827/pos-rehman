-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 06:35 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos-rehman_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `owner_id`, `name`, `location`, `created_at`) VALUES
(1, 2, 'Lahore Branch', 'lahore', '2025-06-27 10:38:11'),
(2, 2, 'Multan Branch', 'Multan', '2025-06-27 10:38:21'),
(4, 4, 'Layari Branch', 'Layari', '2025-11-16 16:04:30');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `owner_id`, `name`, `created_at`) VALUES
(1, 2, 'Electronics', '2025-10-24 07:27:55'),
(2, 2, 'Fashion', '2025-10-24 07:27:55'),
(3, 2, 'Home Appliances', '2025-10-24 07:27:55'),
(4, 2, 'Groceries', '2025-10-24 07:27:55'),
(5, 2, 'Books & Stationery', '2025-10-24 07:27:55'),
(6, 2, 'Health & Beauty', '2025-10-24 07:27:55'),
(7, 2, 'Sports & Fitness', '2025-10-24 07:27:55'),
(8, 2, 'Toys & Kids', '2025-10-24 07:27:55'),
(9, 2, 'Automotive', '2025-10-24 07:27:55'),
(10, 2, 'Mobile Accessories', '2025-10-24 07:27:55');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone_number`, `address`, `branch_id`, `created_at`) VALUES
(1, 'Lahore customer One', 'customer@gmail.com', '3176526827', 'Street jeff xxxx\nAp04', 1, '2025-10-23 10:47:38'),
(2, 'Multan Customer', 'can@gmail.com', '03176526827', 'p/o box basti cheena tehsil jampur district rajanpur\nAp04', 2, '2025-10-23 10:54:01'),
(3, 'from manager', 'member@fd.com', '3221', 'xyz', 1, '2025-10-23 11:01:24'),
(5, 'fromsal', 'fromsale@gmail.com', '03266029050', 'xuz', 1, '2025-10-23 11:22:56');

-- --------------------------------------------------------

--
-- Table structure for table `khata`
--

CREATE TABLE `khata` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `cash_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` enum('CREDIT','DEBIT') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `khata`
--

INSERT INTO `khata` (`id`, `customer_id`, `sale_id`, `amount`, `cash_received`, `type`, `created_at`) VALUES
(1, 1, 2, 5600.00, 8000.00, 'CREDIT', '2025-10-24 12:55:31'),
(2, 1, NULL, -200.00, 200.00, 'DEBIT', '2025-10-24 13:03:40'),
(3, 1, NULL, -300.00, 300.00, 'DEBIT', '2025-10-24 13:03:58'),
(4, 1, 8, -500.00, 5000.00, 'CREDIT', '2025-11-16 16:27:17'),
(5, 1, 10, 99700.00, 20000.00, 'CREDIT', '2025-12-15 12:13:36');

-- --------------------------------------------------------

--
-- Table structure for table `managers`
--

CREATE TABLE `managers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `managers`
--

INSERT INTO `managers` (`id`, `full_name`, `email`, `password`, `branch_id`, `assigned_at`) VALUES
(1, 'Haider ALI', 'haider@gmail.com', '$2y$10$EHVkiTpO0ebx1ocfQVrhfezihS5OK56qhLtyftjOLGolAIjkmVNxu', 1, '2025-06-27 10:41:06'),
(2, 'Multan Branch', 'multan@gmail.com', '$2y$10$Mp87sLPEyNuKA1flSD5cP.fth2z.qdKBV7fzFy1hPeb2Nwn3Ryl7K', 2, '2025-11-16 16:00:56'),
(3, 'Layari Manager', 'layari@gmail.com', '$2y$10$gfzyg/2JOmfJeVpn.K2d7.DWm2YVIw0vUhE6MxR6DmOnuQNph513e', 4, '2025-11-16 16:05:04');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `barcode_id` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category_id` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `branch_id`, `barcode_id`, `name`, `description`, `price`, `quantity`, `low_stock_threshold`, `image`, `created_at`, `category_id`, `unit`) VALUES
(32, 1, '507809066663', 'Smartphone X10', 'Latest 5G smartphone with AMOLED display', 75000.00, 10, 10, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 10, 'Liter'),
(33, 1, '0640947281089', 'Platinum Cigarate', 'High Nicotine', 100.00, 40, 20, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'Milliliter'),
(34, 1, '562391875432', 'Bluetooth Headphones', 'Noise cancelling wireless headphones', 8500.00, 25, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(36, 1, '993457812365', 'LED TV 42\"', 'Full HD Smart LED TV', 65000.00, 8, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(37, 1, '328459781246', 'Wireless Mouse', 'Ergonomic mouse with 2.4GHz receiver', 2500.00, 50, 10, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'Piece'),
(38, 1, '679845231987', 'Gaming Keyboard', 'RGB mechanical gaming keyboard', 9500.00, 4, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(39, 1, '234987561234', 'Power Bank 20000mAh', 'Fast charging portable power bank', 4500.00, 16, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(40, 1, '892374659812', 'Digital Camera Z200', 'Compact digital camera with 4K recording', 88000.00, 2, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(41, 1, '134569782341', 'Bluetooth Speaker', 'Portable speaker with deep bass', 6500.00, 17, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 1, 'pcs'),
(42, 1, '679841235971', 'Men T-Shirt', 'Cotton casual t-shirt for men', 1200.00, 50, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(43, 1, '894512379845', 'Women Dress', 'Floral summer dress', 3500.00, 20, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(44, 1, '235789465123', 'Jeans Pants', 'Slim-fit denim jeans', 2800.00, 25, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(45, 1, '987541236895', 'Leather Jacket', 'Genuine leather biker jacket', 9500.00, 8, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(46, 1, '452136789214', 'Sneakers', 'Stylish running sneakers', 4500.00, 30, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(47, 1, '891245789632', 'Formal Shirt', 'White cotton formal shirt', 1800.00, 25, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(48, 1, '673219845621', 'Women Handbag', 'Leather shoulder handbag', 6000.00, 10, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(49, 1, '583497612345', 'Men Watch', 'Analog wristwatch with metal strap', 7000.00, 15, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(50, 1, '947861235784', 'Sunglasses', 'UV protection polarized sunglasses', 2500.00, 35, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(51, 1, '268945137984', 'Cap', 'Adjustable baseball cap', 900.00, 40, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 2, 'pcs'),
(52, 1, '789451236981', 'Microwave Oven', 'Convection microwave oven 25L', 23000.00, 10, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(53, 1, '341256789451', 'Washing Machine', 'Top load washing machine 7kg', 55000.00, 8, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(54, 1, '678954123687', 'Electric Kettle', '1.8L stainless steel electric kettle', 2800.00, 25, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(55, 1, '984512367845', 'Air Conditioner', '1.5 Ton Split AC with inverter', 95000.00, 6, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(56, 1, '234678945812', 'Ceiling Fan', '3-blade energy-saving fan', 5000.00, 20, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(57, 1, '781239654871', 'Vacuum Cleaner', 'Bagless dry vacuum cleaner', 14500.00, 12, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(58, 1, '395841278954', 'Iron', 'Steam iron with non-stick soleplate', 3000.00, 25, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs'),
(59, 1, '978456123985', 'Refrigerator', 'Double door fridge 300L', 89000.00, 7, 0, 'prod_68fb2ad8c56d7.jpg', '2025-10-24 12:32:51', 3, 'pcs');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `supplier_email` varchar(100) DEFAULT NULL,
  `purchase_date` datetime DEFAULT current_timestamp(),
  `invoice_number` varchar(100) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid') DEFAULT 'pending',
  `payment_method` enum('Cash','Bank','Credit','Mobile') DEFAULT 'Cash',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `branch_id`, `manager_id`, `supplier_name`, `supplier_phone`, `supplier_email`, `purchase_date`, `invoice_number`, `total_amount`, `discount`, `grand_total`, `payment_status`, `payment_method`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'NASIR ABBAS', '03176526827', 'nasiryt.827@gmail.com', '2026-02-03 10:16:54', 'xs', 25000.00, 0.00, 25000.00, 'pending', 'Cash', '2026-02-03 10:16:54', '2026-02-03 10:16:54'),
(2, 1, 1, 'NASIR ABBAS', '03176526827', 'nasiryt.827@gmail.com', '2026-02-03 10:34:07', 'dfadsf', 675000.00, 0.00, 675000.00, 'pending', 'Mobile', '2026-02-03 10:34:07', '2026-02-03 10:34:07');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `line_total` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price` - `discount`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `unit_price`, `discount`) VALUES
(1, 1, 37, 10, 2500.00, 0.00),
(2, 2, 32, 9, 75000.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) NOT NULL,
  `cash_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('Cash','Credit') NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `salesperson_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `sale_date`, `total_amount`, `cash_received`, `payment_method`, `branch_id`, `salesperson_id`, `customer_id`) VALUES
(1, '2026-01-18 12:54:41', 750000.00, 750000.00, 'Cash', 1, 1, 1),
(2, '2026-01-17 12:55:30', 13600.00, 8000.00, 'Credit', 1, 1, 1),
(3, '2026-01-17 12:55:30', 6500.00, 6500.00, 'Cash', 1, 1, 1),
(4, '2026-10-19 12:55:30', 88000.00, 88000.00, 'Cash', 1, 1, 1),
(5, '2025-11-16 16:09:32', 88000.00, 88000.00, 'Cash', 1, 1, 1),
(6, '2025-11-16 16:13:53', 4500.00, 4500.00, 'Cash', 1, 1, 1),
(7, '2025-11-16 16:16:06', 88000.00, 88000.00, 'Cash', 1, 1, 1),
(8, '2025-11-16 16:27:17', 4500.00, 5000.00, 'Credit', 1, 1, 1),
(9, '2025-12-15 12:12:34', 359100.00, 359100.00, 'Cash', 1, 1, 1),
(10, '2025-12-15 12:13:36', 119700.00, 20000.00, 'Credit', 1, 1, 1),
(11, '2025-12-15 12:15:28', 800.00, 800.00, 'Cash', 1, 1, 5),
(12, '2025-12-15 12:16:20', 6500.00, 6500.00, 'Cash', 1, 1, 1),
(13, '2025-12-15 12:16:58', 6500.00, 6500.00, 'Cash', 1, 1, 1),
(14, '2025-12-15 12:19:55', 700.00, 700.00, 'Cash', 1, 1, 3),
(15, '2026-01-18 12:41:45', 64800.00, 64800.00, 'Cash', 1, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `salespersons`
--

CREATE TABLE `salespersons` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salespersons`
--

INSERT INTO `salespersons` (`id`, `name`, `email`, `password`, `branch_id`, `created_at`) VALUES
(1, 'Sales Perosn', 'salep@gmail.com', '$2y$10$O12gqtbdaGYIkiPuuvAL6.eNpgQD0wNkvDcKye7GU.4roMmFuG9rq', 1, '2025-10-23 12:53:29'),
(2, 'Layari Sales Person', 'layariS@gmail.com', '$2y$10$tohCIoePOlADFFgNUpnzUug2GKLrUW8b5qhGBUYhMg24aBWawODmi', 4, '2025-11-16 16:05:32');

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `line_total` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price` - `discount`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_items`
--

INSERT INTO `sales_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `discount`) VALUES
(1, 1, 32, 10, 75000.00, 0.00),
(2, 2, 38, 8, 9500.00, 7800.00),
(3, 3, 41, 1, 6500.00, 0.00),
(4, 4, 40, 1, 88000.00, 0.00),
(5, 5, 40, 1, 88000.00, 0.00),
(6, 6, 39, 1, 4500.00, 0.00),
(7, 7, 40, 1, 88000.00, 0.00),
(8, 8, 39, 1, 4500.00, 0.00),
(9, 9, 33, 3, 120000.00, 300.00),
(10, 10, 33, 1, 120000.00, 300.00),
(11, 11, 33, 10, 100.00, 20.00),
(12, 12, 41, 1, 6500.00, 0.00),
(13, 13, 41, 1, 6500.00, 0.00),
(14, 14, 33, 10, 100.00, 30.00),
(15, 15, 41, 10, 6500.00, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `shop_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `shop_name`, `phone_number`, `created_at`, `status`) VALUES
(2, 'NASIR ABBAS', 'nasiryt.827@gmail.com', '$2y$10$4Ir45.4iiuO39fRHayVch.Wb4qCnCb08OeQyB9SS2cfKQozpXpHC6', 'Nasir Tuck Shop', '+923176526828', '2025-06-27 10:24:12', 'approved'),
(3, 'Haider Ali', 'haider@gmail.com', '$2y$10$SpoH0XOKnZQwZY6NlD/9B.M0SllQhHpJQXg.07VUeb1tKu/C/g9xC', 'Haider Shop', '+923176526827', '2025-10-24 10:25:58', 'approved'),
(4, 'Karachi Owner', 'karachi@gmail.com', '$2y$10$xcdi.ZTPMRm0eWU2vsSlQOir/p5VhS1DvQYoYn7wWVF2rQfnZCNNK', 'Karachi Shop', '+923176526824', '2025-11-16 16:02:06', 'approved');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_fk` (`branch_id`);

--
-- Indexes for table `khata`
--
ALTER TABLE `khata`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_fk` (`customer_id`),
  ADD KEY `sale_fk` (`sale_id`);

--
-- Indexes for table `managers`
--
ALTER TABLE `managers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode_id` (`barcode_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `fk_product_category` (`category_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_fk` (`branch_id`),
  ADD KEY `salesperson_fk` (`salesperson_id`),
  ADD KEY `customer_fk` (`customer_id`);

--
-- Indexes for table `salespersons`
--
ALTER TABLE `salespersons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_fk` (`branch_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_fk` (`sale_id`),
  ADD KEY `product_fk` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `khata`
--
ALTER TABLE `khata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `managers`
--
ALTER TABLE `managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `salespersons`
--
ALTER TABLE `salespersons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `khata`
--
ALTER TABLE `khata`
  ADD CONSTRAINT `khata_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `khata_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `managers`
--
ALTER TABLE `managers`
  ADD CONSTRAINT `managers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `managers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_branch_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_salesperson_fk` FOREIGN KEY (`salesperson_id`) REFERENCES `salespersons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `salespersons`
--
ALTER TABLE `salespersons`
  ADD CONSTRAINT `salespersons_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `sales_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_items_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
