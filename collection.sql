-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2024 at 11:08 AM
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
-- Database: `collection`
--

-- --------------------------------------------------------

--
-- Table structure for table `collected`
--

CREATE TABLE `collected` (
  `id` int(11) NOT NULL,
  `transaction_number` int(11) NOT NULL,
  `collected_date` datetime NOT NULL,
  `collector` varchar(100) NOT NULL,
  `branch` varchar(100) NOT NULL,
  `tenantcode` varchar(100) NOT NULL,
  `spacecode` varchar(100) NOT NULL,
  `tenantname` varchar(100) NOT NULL,
  `rent` varchar(100) NOT NULL,
  `runningbal` varchar(100) NOT NULL,
  `paidrent` varchar(100) NOT NULL,
  `paidbal` varchar(100) NOT NULL,
  `newbalance` varchar(100) NOT NULL,
  `user_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collected`
--

INSERT INTO `collected` (`id`, `transaction_number`, `collected_date`, `collector`, `branch`, `tenantcode`, `spacecode`, `tenantname`, `rent`, `runningbal`, `paidrent`, `paidbal`, `newbalance`, `user_email`) VALUES
(40, 2, '2024-05-17 17:21:45', 'basagre', 'Ambulant', '55', 'S07', 'ABDUL JABBER MACATOON', '895.4', '1340.4', '66', '66', '2103.8', 'aianbasagre@gmail.com'),
(41, 3, '2024-05-17 17:43:56', 'basagre', 'Nova Market', '23', 'S06', 'SAID MAMBUAY', '770', '1599', '44', '44', '2281', 'aianbasagre@gmail.com'),
(42, 4, '2024-05-17 17:47:10', 'basagre', 'Sanko Market', 'r', 'S47', 'EVA BAUTISTA', '847', '4840', '55', '55', '5577', 'aianbasagre@gmail.com'),
(43, 5, '2024-05-21 14:17:43', 'basagre', 'Nova Market', 'test', 'S03', 'JHAMAEL MACATOON', '825.83', '9037.74', '66', '66', '9731.57', 'aianbasagre@gmail.com'),
(44, 6, '2024-05-28 17:41:53', 'basagre', 'Nova Market', 't4', 'test', 'test', '2', '2', '2', '2', '0', 'aianbasagre@gmail.com'),
(45, 7, '2024-05-28 17:48:43', 'basagre', 'Nova Market', 't4', 'test', 'test', '2', '2', '2', '2', '0', 'aianbasagre@gmail.com'),
(46, 8, '2024-05-28 17:49:07', 'basagre', 'Ambulant', 'try', 'try', 'try', '3', '3', '3', '3', '0', 'aianbasagre@gmail.com'),
(47, 9, '2024-05-28 17:50:54', 'basagre', 'Nova Market', '4', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(48, 10, '2024-05-30 09:04:03', 'basagre', 'Sanko Market', 'test', 'S-01', 'AMNAH BASHER IMAM', '866', '10004.35', '55', '55', '10760.35', 'aianbasagre@gmail.com'),
(49, 11, '2024-05-30 09:04:30', 'basagre', 'Nova Market', 't', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(50, 12, '2024-06-04 08:55:59', 'basagre', 'Nova Market', 'Aiannnn', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(51, 13, '2024-06-04 08:59:33', 'Gelo', 'Ambulant', 'St', 'try', 'try', '34', '0', '23', '23', '-12', 'angelogayda33@gmail.com'),
(52, 14, '2024-06-04 09:20:10', 'basagre', 'Nova Market', 'Aiannnn', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(53, 15, '2024-06-04 13:39:56', 'basagre', 'Nova Market', '2', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(54, 16, '2024-06-04 13:49:33', 'basagre', 'Nova Market', '10', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(55, 17, '2024-06-04 14:02:24', 'Gelo', 'Ambulant', '123', 'try', 'try', '34', '-12', '123', '123', '-224', 'angelogayda33@gmail.com'),
(56, 18, '2024-06-04 14:11:55', 'basagre', 'Ambulant', 't', 'try', 'try', '34', '-224', '4', '224', '-418', 'aianbasagre@gmail.com'),
(57, 19, '2024-06-04 14:13:04', 'basagre', 'Nova Market', 'yey', 'test', 'test', '2', '0', '2', '0', '0', 'aianbasagre@gmail.com'),
(58, 20, '2024-06-04 14:18:04', 'basagre', 'Ambulant', 'ret', 'try', 'try', '34', '-418', '4', '4', '-392', 'aianbasagre@gmail.com'),
(59, 21, '2024-06-04 15:34:11', 'basagre', 'Ambulant', 'h', 'try', 'try', '34', '-392', '4', '4', '-366', 'aianbasagre@gmail.com'),
(60, 22, '2024-06-07 14:43:27', 'basagre', 'Sanko Market', 'test', 'Ambulant', 'test', '100', '100', '100', '60', '40', 'aianbasagre@gmail.com'),
(61, 23, '2024-06-07 14:44:56', 'basagre', 'Nova Market', '2', 'Ambulant', '22', '2', '2', '2', '2', '0', 'aianbasagre@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `nova`
--

CREATE TABLE `nova` (
  `id` int(11) NOT NULL,
  `tenantname` varchar(100) NOT NULL,
  `spacecode` varchar(100) NOT NULL,
  `daily` varchar(100) NOT NULL,
  `runningbal` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nova`
--

INSERT INTO `nova` (`id`, `tenantname`, `spacecode`, `daily`, `runningbal`) VALUES
(46, '', 'Ambulant', '0', '0');

-- --------------------------------------------------------

--
-- Table structure for table `sanko`
--

CREATE TABLE `sanko` (
  `id` int(11) NOT NULL,
  `tenantname` varchar(100) NOT NULL,
  `spacecode` varchar(100) NOT NULL,
  `daily` varchar(100) NOT NULL,
  `runningbal` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sanko`
--

INSERT INTO `sanko` (`id`, `tenantname`, `spacecode`, `daily`, `runningbal`) VALUES
(1, 'AMNAH BASHER IMAM', 'S-01', '866', '14622'),
(2, 'JHAMAEL MADUM MACATOON', 'S-02', '825.83', '691.83'),
(3, 'JHAMAEL MADUM MACATOON', 'S-03', '825.83', '631.58'),
(4, 'SAID MACARUPONG MAMBUAY', 'S-07', '770', '0'),
(5, 'ABDUL JABBAR M. MACATO-ON', 'S-08', '895.4', '496.40'),
(6, 'SAHANI ACOT RAZUL', 'S-09', '764.50', '0'),
(7, 'NORHAYA D. MACATOON', 'S-10', '889.35', '9681.35'),
(8, 'JHAMAEL MADUM MACATOON', 'S-39', '808.5', '733.50'),
(9, 'JHAMAEL MADUM MACATOON', 'S-23', '808.5', '733.50'),
(10, 'JHAMAEL MADUM MACATOON', 'S-22', '808.50', '0'),
(11, 'JABBER MADUM MACATOON', 'S-40', '847', '3071'),
(12, 'RICO ENAJE', 'S-47', '600.00', '0'),
(13, 'CESAR/ANA STA. ANA', 'S-50', '1149.50', '0'),
(14, 'EVA S. BAUTISTA', 'S-48', '847.00', '2451.00'),
(15, 'JONATHAN B. LOMIBAO', 'S-32 / S-44', '1655.5', '1783.25'),
(16, 'ALIASGAR GUNDUL MANGONDACAN', 'S-06', '770.00', '770.00'),
(17, 'JHAMAEL MADUM MACATOON', 'S-14', '577.50', '0'),
(18, 'ERLINDA PONCE', 'S-04', '880', '14820'),
(19, 'ERLINDA PONCE', 'S-13', '550', '8800'),
(20, 'LOUDRES S. MAGSILAT', 'S-5/S-12', '1813.5', '17168.50'),
(21, 'MARIVIC PALLERA', 'S-41 / S-51', '5802.50', '0'),
(22, 'ALEX SALO', 'S-45', '891', '1783'),
(23, 'EDGAR ENAJE', 'S-26', '620.00', '20.00'),
(24, 'CARMELITA RABOR', 'S-33 / S-34', '682.00', '0'),
(25, 'ERLINDA CUYSONA', 'S-37 / S-38', '629.2', '1777.30'),
(26, 'RODEL SOLIMAN CHANGCOCO', 'S-35', '847', '5220'),
(27, 'MYRNA ALCANTARA SOLEDAD', 'S-27/S-28/S-29/S-30', '1125.3', '8514.30'),
(28, 'BENJIE A. MAGTALAS', 'S-20/S-24/S-25', '847', '16817'),
(29, 'CLEO ANDREA ALBON', 'S-19', '440.00', '980.00'),
(30, 'ROBERTO M. TURLA', 'S-11', '413', '18333'),
(31, 'JONATHAN ABALLA VERGARA', 'S-36/S-42/S-43', '1457.5', '3985'),
(32, 'JONATHAN ABALLA VERGARA', 'S-14/S-15/S-16/S-17', '660.00', '2800.00'),
(33, 'JONATHAN ABALLA VERGARA', 'VACANT SPACE', '150.00', '650.00'),
(35, '', 'Ambulant', '0', '0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_type` varchar(100) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `lname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `branch` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_type`, `fname`, `lname`, `email`, `branch`, `password`) VALUES
(1, 'normal_user', 'aian', 'basagre', 'aianbasagre@gmail.com', 'Sanko Market', 'Aian24'),
(2, 'admin', 'admin', 'admin', 'admin', 'admin', 'admin'),
(8, 'normal_user', 'Gelo', 'Gelo', 'angelogayda33@gmail.com', 'Hsksn', '123456789');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `collected`
--
ALTER TABLE `collected`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nova`
--
ALTER TABLE `nova`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sanko`
--
ALTER TABLE `sanko`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `collected`
--
ALTER TABLE `collected`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `nova`
--
ALTER TABLE `nova`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `sanko`
--
ALTER TABLE `sanko`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
