-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 06, 2025 at 10:10 AM
-- Server version: 8.0.42
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vxjtgclw_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', 'User logged in', '41.209.14.78', '2025-10-05 23:00:44'),
(2, 1, 'Create Project', 'Created project: Baraka Gardens', '41.209.14.78', '2025-10-05 23:08:43'),
(3, 1, 'Create Plot', 'Created plot: 1', '41.209.14.78', '2025-10-05 23:10:21'),
(4, 1, 'Create Project', 'Created project: Mwalimu Farm Phase 3', '41.209.14.78', '2025-10-05 23:11:50'),
(5, 1, 'Update User', 'Updated user: Admin', '41.209.14.78', '2025-10-05 23:15:19'),
(6, 1, 'Create Site Visit', 'Created site visit: Clients Site Visit', '41.209.14.78', '2025-10-05 23:17:34'),
(7, 1, 'Create Client', 'Created client: Dennis Mwangi', '41.209.14.78', '2025-10-05 23:25:27'),
(8, 1, 'Create Plot', 'Created plot: 6', '41.209.14.78', '2025-10-06 00:14:34'),
(9, 1, 'Login', 'User logged in', '41.209.14.78', '2025-10-06 07:05:07'),
(10, 1, 'Create User', 'Created user: Juice', '41.209.14.78', '2025-10-06 07:06:54'),
(11, 1, 'Create Sale', 'Created sale ID: 1', '41.209.14.78', '2025-10-06 07:07:47');

-- --------------------------------------------------------

--
-- Table structure for table `ai_predictions`
--

CREATE TABLE `ai_predictions` (
  `id` int NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `prediction_type` varchar(50) DEFAULT NULL,
  `prediction_data` json DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_dashboards`
--

CREATE TABLE `analytics_dashboards` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` json DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `clock_in_latitude` decimal(10,8) DEFAULT NULL,
  `clock_in_longitude` decimal(11,8) DEFAULT NULL,
  `clock_out_latitude` decimal(10,8) DEFAULT NULL,
  `clock_out_longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('present','late','half_day') DEFAULT 'present',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `target_audience` json DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `status` enum('draft','scheduled','running','completed','paused') DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `stats` json DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_recipients`
--

CREATE TABLE `campaign_recipients` (
  `id` int NOT NULL,
  `campaign_id` int NOT NULL,
  `recipient_type` enum('lead','client') DEFAULT NULL,
  `recipient_id` int NOT NULL,
  `status` enum('pending','sent','failed','opened','clicked') DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_conversations`
--

CREATE TABLE `chatbot_conversations` (
  `id` int NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `lead_id` int DEFAULT NULL,
  `messages` json DEFAULT NULL,
  `status` enum('active','ended','transferred') DEFAULT NULL,
  `transferred_to` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `address` text,
  `assigned_agent` int DEFAULT NULL,
  `lead_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `full_name`, `email`, `phone`, `id_number`, `address`, `assigned_agent`, `lead_id`, `created_at`, `updated_at`) VALUES
(2, 'Dennis Mwangi', 'mwangidennis546@gmail.com', '0758256440', '1234567890', 'Ruiru', NULL, NULL, '2025-10-05 23:25:27', '2025-10-05 23:25:27');

-- --------------------------------------------------------

--
-- Table structure for table `client_documents`
--

CREATE TABLE `client_documents` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_portal_access`
--

CREATE TABLE `client_portal_access` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communications`
--

CREATE TABLE `communications` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `recipient_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `related_type` enum('lead','client','site_visit','general') DEFAULT 'general',
  `related_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communication_templates`
--

CREATE TABLE `communication_templates` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('email','sms','whatsapp') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text,
  `variables` json DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `related_type` enum('client','sale','project','general') NOT NULL,
  `related_id` int DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `template_file` varchar(500) DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_targets`
--

CREATE TABLE `employee_targets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_value` decimal(15,2) DEFAULT NULL,
  `achieved_value` decimal(15,2) DEFAULT NULL,
  `period` varchar(50) DEFAULT NULL,
  `status` enum('active','completed','failed') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generated_documents`
--

CREATE TABLE `generated_documents` (
  `id` int NOT NULL,
  `template_id` int DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `signature_status` enum('pending','signed','expired') DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `generated_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `source` enum('facebook','instagram','website','referral','walk_in','other') NOT NULL,
  `status` enum('new','contacted','qualified','negotiation','converted','lost') DEFAULT 'new',
  `assigned_to` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_activities`
--

CREATE TABLE `lead_activities` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `activity_type` varchar(50) DEFAULT NULL,
  `activity_data` json DEFAULT NULL,
  `score_impact` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_progress`
--

CREATE TABLE `lead_progress` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `status` varchar(100) NOT NULL,
  `notes` text,
  `updated_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_scores`
--

CREATE TABLE `lead_scores` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `score` int DEFAULT '0',
  `factors` json DEFAULT NULL,
  `grade` enum('A','B','C','D','E') DEFAULT NULL,
  `last_calculated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_devices`
--

CREATE TABLE `mobile_devices` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_type` enum('ios','android') DEFAULT NULL,
  `push_token` varchar(500) DEFAULT NULL,
  `app_version` varchar(20) DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'Test Notification', 'This is a test notification created at 02:33 AM', 'info', '/dashboard.php', 0, '2025-10-05 23:33:03'),
(2, 2, 'New Sale Recorded', 'A new sale has been recorded', 'success', '/sales.php?action=view&id=1', 0, '2025-10-06 07:07:47');

-- --------------------------------------------------------

--
-- Table structure for table `online_payments`
--

CREATE TABLE `online_payments` (
  `id` int NOT NULL,
  `sale_id` int NOT NULL,
  `gateway_id` int NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','refunded') DEFAULT NULL,
  `gateway_response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `sale_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','mpesa','card') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `received_by` int NOT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `config` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL,
  `allowances` decimal(15,2) DEFAULT '0.00',
  `commissions` decimal(15,2) DEFAULT '0.00',
  `deductions` decimal(15,2) DEFAULT '0.00',
  `net_salary` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `reviewer_id` int NOT NULL,
  `period` varchar(50) DEFAULT NULL,
  `ratings` json DEFAULT NULL,
  `comments` text,
  `goals` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `plot_number` varchar(50) NOT NULL,
  `section` varchar(100) DEFAULT NULL,
  `size` decimal(10,2) NOT NULL COMMENT 'Size in square meters or acres',
  `price` decimal(15,2) NOT NULL,
  `status` enum('available','booked','sold') DEFAULT 'available',
  `position_x` int DEFAULT NULL COMMENT 'X coordinate for map layout',
  `position_y` int DEFAULT NULL COMMENT 'Y coordinate for map layout',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`id`, `project_id`, `plot_number`, `section`, `size`, `price`, `status`, `position_x`, `position_y`, `created_at`, `updated_at`) VALUES
(1, 1, '1', 'Slot 1', 80.00, 550000.00, 'sold', NULL, NULL, '2025-10-05 23:10:21', '2025-10-06 07:07:47'),
(2, 2, '6', 'Block A', 50.00, 885000.00, 'booked', NULL, NULL, '2025-10-06 00:14:34', '2025-10-06 00:14:34');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `total_plots` int NOT NULL,
  `description` text,
  `status` enum('active','completed','on_hold') DEFAULT 'active',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `location`, `total_plots`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Baraka Gardens', 'Juja Farm', 8, 'Baraka Gardens Phase 1 in Juja Farm was an exceptional opportunity for investors and homeowners seeking affordable residential plots in a well-developed area. This project offered varied plot sizes with ready freehold title deeds, positioned in the Athi-Mukuyu-ini area of Juja Farm.\r\n\r\nJuja has emerged as one of Nairobi&#039;s most sought-after satellite towns, offering the perfect balance between urban convenience and suburban tranquility. Property values in Juja have appreciated significantly, making this one of the most lucrative real estate investment areas in the Nairobi metropolitan region.', 'active', 1, '2025-10-05 23:08:43', '2025-10-05 23:08:43'),
(2, 'Mwalimu Farm Phase 3', 'Ruiru', 3, 'Mwalimu Farm Phase 3 in Ruiru East presents an exceptional opportunity for investors and homeowners seeking premium residential plots in Nairobi&#039;s fastest-growing satellite town. This well-planned development offers 50×100 plots with ready title deeds, positioned just 30km from Nairobi CBD along the Eastern Bypass corridor.\r\n\r\nRuiru has emerged as one of Nairobi&#039;s most sought-after residential areas, offering the perfect balance between urban convenience and suburban tranquility. Property values in Ruiru East have appreciated by over 25% annually, making this one of the most lucrative real estate investments in the Nairobi metropolitan area.', 'active', 1, '2025-10-05 23:11:50', '2025-10-05 23:11:50');

-- --------------------------------------------------------

--
-- Table structure for table `push_notifications`
--

CREATE TABLE `push_notifications` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text,
  `data` json DEFAULT NULL,
  `status` enum('pending','sent','failed','read') DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int NOT NULL,
  `referrer_id` int NOT NULL,
  `referrer_type` enum('client','agent') DEFAULT NULL,
  `referred_lead_id` int DEFAULT NULL,
  `program_id` int DEFAULT NULL,
  `status` enum('pending','qualified','converted','paid') DEFAULT NULL,
  `reward_amount` decimal(15,2) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_programs`
--

CREATE TABLE `referral_programs` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `reward_type` varchar(50) DEFAULT NULL,
  `reward_value` decimal(15,2) DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_templates`
--

CREATE TABLE `report_templates` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `template` json DEFAULT NULL,
  `schedule` varchar(50) DEFAULT NULL,
  `recipients` json DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int NOT NULL,
  `role` enum('admin','manager','sales_agent','finance','reception') NOT NULL,
  `module` varchar(100) NOT NULL,
  `can_view` tinyint(1) DEFAULT '0',
  `can_create` tinyint(1) DEFAULT '0',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(1, 'admin', 'dashboard', 1, 1, 1, 1),
(2, 'admin', 'projects', 1, 1, 1, 1),
(3, 'admin', 'plots', 1, 1, 1, 1),
(4, 'admin', 'leads', 1, 1, 1, 1),
(5, 'admin', 'clients', 1, 1, 1, 1),
(6, 'admin', 'sales', 1, 1, 1, 1),
(7, 'admin', 'payments', 1, 1, 1, 1),
(8, 'admin', 'users', 1, 1, 1, 1),
(9, 'admin', 'attendance', 1, 1, 1, 1),
(10, 'admin', 'payroll', 1, 1, 1, 1),
(11, 'admin', 'site_visits', 1, 1, 1, 1),
(12, 'admin', 'documents', 1, 1, 1, 1),
(13, 'admin', 'reports', 1, 1, 1, 1),
(14, 'admin', 'settings', 1, 1, 1, 1),
(15, 'manager', 'dashboard', 1, 1, 1, 1),
(16, 'manager', 'projects', 1, 1, 1, 1),
(17, 'manager', 'plots', 1, 1, 1, 0),
(18, 'manager', 'leads', 1, 1, 1, 0),
(19, 'manager', 'clients', 1, 1, 1, 0),
(20, 'manager', 'sales', 1, 1, 1, 0),
(21, 'manager', 'payments', 1, 0, 0, 0),
(22, 'manager', 'users', 1, 1, 1, 0),
(23, 'manager', 'attendance', 1, 1, 1, 0),
(24, 'manager', 'payroll', 1, 0, 0, 0),
(25, 'manager', 'site_visits', 1, 1, 1, 1),
(26, 'manager', 'documents', 1, 1, 1, 0),
(27, 'manager', 'reports', 1, 1, 1, 0),
(28, 'manager', 'settings', 0, 0, 0, 0),
(29, 'sales_agent', 'dashboard', 1, 0, 0, 0),
(30, 'sales_agent', 'projects', 1, 0, 0, 0),
(31, 'sales_agent', 'plots', 1, 0, 0, 0),
(32, 'sales_agent', 'leads', 1, 1, 1, 0),
(33, 'sales_agent', 'clients', 1, 1, 1, 0),
(34, 'sales_agent', 'sales', 1, 1, 1, 0),
(35, 'sales_agent', 'payments', 0, 0, 0, 0),
(36, 'sales_agent', 'users', 0, 0, 0, 0),
(37, 'sales_agent', 'attendance', 1, 1, 0, 0),
(38, 'sales_agent', 'payroll', 1, 0, 0, 0),
(39, 'sales_agent', 'site_visits', 1, 1, 1, 0),
(40, 'sales_agent', 'documents', 1, 1, 0, 0),
(41, 'sales_agent', 'reports', 0, 0, 0, 0),
(42, 'sales_agent', 'settings', 0, 0, 0, 0),
(43, 'finance', 'dashboard', 1, 0, 0, 0),
(44, 'finance', 'projects', 1, 0, 0, 0),
(45, 'finance', 'plots', 1, 0, 0, 0),
(46, 'finance', 'leads', 0, 0, 0, 0),
(47, 'finance', 'clients', 1, 0, 0, 0),
(48, 'finance', 'sales', 1, 0, 1, 0),
(49, 'finance', 'payments', 1, 1, 1, 0),
(50, 'finance', 'users', 0, 0, 0, 0),
(51, 'finance', 'attendance', 1, 1, 0, 0),
(52, 'finance', 'payroll', 1, 1, 1, 0),
(53, 'finance', 'site_visits', 0, 0, 0, 0),
(54, 'finance', 'documents', 1, 1, 0, 0),
(55, 'finance', 'reports', 1, 1, 0, 0),
(56, 'finance', 'settings', 0, 0, 0, 0),
(57, 'reception', 'dashboard', 1, 0, 0, 0),
(58, 'reception', 'projects', 1, 0, 0, 0),
(59, 'reception', 'plots', 1, 0, 0, 0),
(60, 'reception', 'leads', 1, 1, 1, 0),
(61, 'reception', 'clients', 1, 1, 1, 0),
(62, 'reception', 'sales', 0, 0, 0, 0),
(63, 'reception', 'payments', 0, 0, 0, 0),
(64, 'reception', 'users', 0, 0, 0, 0),
(65, 'reception', 'attendance', 1, 1, 0, 0),
(66, 'reception', 'payroll', 0, 0, 0, 0),
(67, 'reception', 'site_visits', 1, 1, 1, 0),
(68, 'reception', 'documents', 1, 1, 0, 0),
(69, 'reception', 'reports', 0, 0, 0, 0),
(70, 'reception', 'settings', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `plot_id` int NOT NULL,
  `agent_id` int NOT NULL,
  `sale_price` decimal(15,2) NOT NULL,
  `deposit_amount` decimal(15,2) DEFAULT '0.00',
  `balance` decimal(15,2) NOT NULL,
  `payment_plan` enum('full_payment','installment') DEFAULT 'installment',
  `sale_date` date NOT NULL,
  `status` enum('pending','active','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `client_id`, `plot_id`, `agent_id`, `sale_price`, `deposit_amount`, `balance`, `payment_plan`, `sale_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 2, 550000.00, 0.00, 550000.00, 'full_payment', '2025-10-06', 'active', '2025-10-06 07:07:47', '2025-10-06 07:07:47');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `site_name` varchar(255) DEFAULT 'Zuri CRM',
  `primary_color` varchar(7) DEFAULT '#0C3807',
  `secondary_color` varchar(7) DEFAULT '#FF6B35',
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_address` text,
  `office_latitude` decimal(10,8) DEFAULT NULL,
  `office_longitude` decimal(11,8) DEFAULT NULL,
  `office_radius` int DEFAULT '100',
  `logo_path` varchar(255) DEFAULT '/logo.png',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `site_name`, `primary_color`, `secondary_color`, `contact_email`, `contact_phone`, `contact_address`, `office_latitude`, `office_longitude`, `office_radius`, `logo_path`, `updated_at`) VALUES
(1, 'Zuri CRM', '#0C3807', '#FF6B35', 'info@zuricrm.com', '+254700000000', NULL, -1.29210000, 36.82190000, 100, '/logo.png', '2025-10-05 22:11:45');

-- --------------------------------------------------------

--
-- Table structure for table `site_visits`
--

CREATE TABLE `site_visits` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `visit_date` datetime NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `site_visits`
--

INSERT INTO `site_visits` (`id`, `project_id`, `visit_date`, `title`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-10-11 02:16:00', 'Clients Site Visit', '', 'scheduled', 1, '2025-10-05 23:17:34', '2025-10-05 23:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `site_visit_attendees`
--

CREATE TABLE `site_visit_attendees` (
  `id` int NOT NULL,
  `site_visit_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `attendance_status` enum('pending','confirmed','attended','missed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `site_visit_attendees`
--

INSERT INTO `site_visit_attendees` (`id`, `site_visit_id`, `user_id`, `client_id`, `attendance_status`, `created_at`) VALUES
(1, 1, 1, NULL, 'pending', '2025-10-05 23:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int NOT NULL,
  `ticket_number` varchar(50) DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` text,
  `priority` enum('low','medium','high','urgent') DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` int DEFAULT NULL,
  `assigned_by` int NOT NULL,
  `related_to` varchar(50) DEFAULT NULL,
  `related_id` int DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_responses`
--

CREATE TABLE `ticket_responses` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `message` text,
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','sales_agent','finance','reception') NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `profile_image`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'info@zurihub.co.ke', '+2547 5825 6440', '$2y$10$mkR7/SN.FTzu.qgpnN/Rp.LxFHxTdL7shssLhRqRgCxOSmzSXRcSG', 'admin', NULL, 'active', '2025-10-05 22:11:46', '2025-10-05 23:15:19'),
(2, 'Juice', 'juicemwangi7@gmail.com', '0758256440', '$2y$10$YK30maUWreHge1/8bHI/R.O0Ovew.F00woUQzbdOIyihxPeJbZtvG', 'sales_agent', NULL, 'active', '2025-10-06 07:06:54', '2025-10-06 07:06:54');

-- --------------------------------------------------------

--
-- Table structure for table `workflows`
--

CREATE TABLE `workflows` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `trigger_event` varchar(100) DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `actions` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ai_predictions`
--
ALTER TABLE `ai_predictions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `analytics_dashboards`
--
ALTER TABLE `analytics_dashboards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campaign_recipients`
--
ALTER TABLE `campaign_recipients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_agent` (`assigned_agent`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `client_documents`
--
ALTER TABLE `client_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_portal_access`
--
ALTER TABLE `client_portal_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `communications`
--
ALTER TABLE `communications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `communication_templates`
--
ALTER TABLE `communication_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_targets`
--
ALTER TABLE `employee_targets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lead_progress`
--
ALTER TABLE `lead_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `lead_scores`
--
ALTER TABLE `lead_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mobile_devices`
--
ALTER TABLE `mobile_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `online_payments`
--
ALTER TABLE `online_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_month_year` (`user_id`,`month`,`year`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_plot` (`project_id`,`plot_number`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `push_notifications`
--
ALTER TABLE `push_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_programs`
--
ALTER TABLE `referral_programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_templates`
--
ALTER TABLE `report_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_module` (`role`,`module`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_visits`
--
ALTER TABLE `site_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `site_visit_attendees`
--
ALTER TABLE `site_visit_attendees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_visit_id` (`site_visit_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_responses`
--
ALTER TABLE `ticket_responses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workflows`
--
ALTER TABLE `workflows`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ai_predictions`
--
ALTER TABLE `ai_predictions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_dashboards`
--
ALTER TABLE `analytics_dashboards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_recipients`
--
ALTER TABLE `campaign_recipients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client_documents`
--
ALTER TABLE `client_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_portal_access`
--
ALTER TABLE `client_portal_access`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communications`
--
ALTER TABLE `communications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communication_templates`
--
ALTER TABLE `communication_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_targets`
--
ALTER TABLE `employee_targets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generated_documents`
--
ALTER TABLE `generated_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_activities`
--
ALTER TABLE `lead_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_progress`
--
ALTER TABLE `lead_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_scores`
--
ALTER TABLE `lead_scores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mobile_devices`
--
ALTER TABLE `mobile_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `online_payments`
--
ALTER TABLE `online_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `push_notifications`
--
ALTER TABLE `push_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_programs`
--
ALTER TABLE `referral_programs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_templates`
--
ALTER TABLE `report_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_visits`
--
ALTER TABLE `site_visits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_visit_attendees`
--
ALTER TABLE `site_visit_attendees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_responses`
--
ALTER TABLE `ticket_responses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`assigned_agent`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clients_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `client_portal_access`
--
ALTER TABLE `client_portal_access`
  ADD CONSTRAINT `client_portal_access_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

--
-- Constraints for table `communications`
--
ALTER TABLE `communications`
  ADD CONSTRAINT `communications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `communications_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lead_progress`
--
ALTER TABLE `lead_progress`
  ADD CONSTRAINT `lead_progress_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_progress_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `lead_scores`
--
ALTER TABLE `lead_scores`
  ADD CONSTRAINT `lead_scores_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `plots`
--
ALTER TABLE `plots`
  ADD CONSTRAINT `plots_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `site_visits`
--
ALTER TABLE `site_visits`
  ADD CONSTRAINT `site_visits_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `site_visits_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `site_visit_attendees`
--
ALTER TABLE `site_visit_attendees`
  ADD CONSTRAINT `site_visit_attendees_ibfk_1` FOREIGN KEY (`site_visit_id`) REFERENCES `site_visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `site_visit_attendees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `site_visit_attendees_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
