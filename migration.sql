-- ====================================================================
-- Database Migration Script for Collection POS
-- Safe & Idempotent Schema Update for Electricity & Water Integration
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB, and phpMyAdmin
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- --------------------------------------------------------------------
-- 1. Create Core Supporting Tables if they do not exist
-- --------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_name` (`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `void` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(255) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `note` text DEFAULT NULL,
  `void_date` datetime NOT NULL,
  `rent` decimal(10,2) DEFAULT NULL,
  `rentbal` decimal(10,2) DEFAULT NULL,
  `runningbal` decimal(10,2) DEFAULT NULL,
  `paidrent` decimal(10,2) DEFAULT NULL,
  `paidbal` decimal(10,2) DEFAULT NULL,
  `charges` varchar(225) DEFAULT NULL,
  `collector` varchar(255) DEFAULT NULL,
  `tenantname` varchar(255) DEFAULT NULL,
  `spacecode` varchar(255) DEFAULT NULL,
  `elecbal` varchar(100) DEFAULT '0',
  `paidelec` varchar(100) DEFAULT '0',
  `paidelecarrear` varchar(100) DEFAULT '0',
  `waterbal` varchar(100) DEFAULT '0',
  `paidwater` varchar(100) DEFAULT '0',
  `paidwaterarrear` varchar(100) DEFAULT '0',
  `elecarrear` varchar(100) DEFAULT '0',
  `waterarrear` varchar(100) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `action` enum('created','updated','deleted') NOT NULL,
  `tenant_name` varchar(100) NOT NULL,
  `tenant_code` varchar(100) DEFAULT NULL,
  `space_code` varchar(100) DEFAULT NULL,
  `daily_rent` varchar(100) DEFAULT NULL,
  `rent_balance` varchar(100) DEFAULT NULL,
  `running_balance` varchar(100) DEFAULT NULL,
  `branch` varchar(100) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `changes_made` text DEFAULT NULL,
  `date` date NOT NULL,
  `elec_balance` varchar(100) DEFAULT '0',
  `water_balance` varchar(100) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- 2. Stored Procedure for Safe Column Additions (No Duplicate Column Errors)
-- --------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`;

DELIMITER $$
CREATE PROCEDURE `AddColumnIfNotExists`(
    IN target_table VARCHAR(128),
    IN target_column VARCHAR(128),
    IN column_definition VARCHAR(255)
)
BEGIN
    DECLARE table_count INT;
    DECLARE col_count INT;

    -- Check if table exists
    SELECT COUNT(*) INTO table_count 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = target_table;

    IF table_count > 0 THEN
        -- Check if column exists
        SELECT COUNT(*) INTO col_count 
        FROM information_schema.columns 
        WHERE table_schema = DATABASE() AND table_name = target_table AND column_name = target_column;

        IF col_count = 0 THEN
            SET @ddl = CONCAT('ALTER TABLE `', target_table, '` ADD COLUMN `', target_column, '` ', column_definition);
            PREPARE stmt FROM @ddl;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$
DELIMITER ;

-- --------------------------------------------------------------------
-- 3. Apply Schema Upgrades to Tenant Tables
-- --------------------------------------------------------------------

-- Sanko Table
CALL AddColumnIfNotExists('sanko', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'rentbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'runningbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'daily', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('sanko', 'started_date', 'VARCHAR(100) NULL DEFAULT ""');

-- Nova Table
CALL AddColumnIfNotExists('nova', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'rentbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'runningbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'daily', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('nova', 'started_date', 'VARCHAR(100) NULL DEFAULT ""');

-- APM Table
CALL AddColumnIfNotExists('apm', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'rentbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'runningbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'daily', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('apm', 'started_date', 'VARCHAR(100) NULL DEFAULT ""');

-- ACC Table (if exists)
CALL AddColumnIfNotExists('acc', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'rentbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'runningbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'daily', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('acc', 'started_date', 'VARCHAR(100) NULL DEFAULT ""');

-- --------------------------------------------------------------------
-- 4. Apply Schema Upgrades to Collection Tables
-- --------------------------------------------------------------------

-- Collected (Sanko) Table
CALL AddColumnIfNotExists('collected', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'paidelec', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'newelecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'newelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'paidelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'paidwater', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'newwaterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'newwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'paidwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collected', 'payment_method', 'VARCHAR(50) NULL DEFAULT "Cash"');
CALL AddColumnIfNotExists('collected', 'cheque_number', 'VARCHAR(100) NULL');
CALL AddColumnIfNotExists('collected', 'cheque_payee', 'VARCHAR(255) NULL');

-- Collectednova Table
CALL AddColumnIfNotExists('collectednova', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'paidelec', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'newelecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'newelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'paidelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'paidwater', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'newwaterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'newwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'paidwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectednova', 'payment_method', 'VARCHAR(50) NULL DEFAULT "Cash"');
CALL AddColumnIfNotExists('collectednova', 'cheque_number', 'VARCHAR(100) NULL');
CALL AddColumnIfNotExists('collectednova', 'cheque_payee', 'VARCHAR(255) NULL');

-- Collectedapm Table
CALL AddColumnIfNotExists('collectedapm', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'paidelec', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'newelecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'newelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'paidelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'paidwater', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'newwaterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'newwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'paidwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedapm', 'payment_method', 'VARCHAR(50) NULL DEFAULT "Cash"');
CALL AddColumnIfNotExists('collectedapm', 'cheque_number', 'VARCHAR(100) NULL');
CALL AddColumnIfNotExists('collectedapm', 'cheque_payee', 'VARCHAR(255) NULL');

-- Collectedacc Table (if exists)
CALL AddColumnIfNotExists('collectedacc', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'paidelec', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'newelecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'newelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'paidelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'paidwater', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'newwaterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'newwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'paidwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('collectedacc', 'payment_method', 'VARCHAR(50) NULL DEFAULT "Cash"');
CALL AddColumnIfNotExists('collectedacc', 'cheque_number', 'VARCHAR(100) NULL');
CALL AddColumnIfNotExists('collectedacc', 'cheque_payee', 'VARCHAR(255) NULL');

-- --------------------------------------------------------------------
-- 5. Apply Schema Upgrades to Void Table
-- --------------------------------------------------------------------

CALL AddColumnIfNotExists('void', 'elecbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'paidelec', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'paidelecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'waterbal', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'paidwater', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'paidwaterarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'elecarrear', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('void', 'waterarrear', 'VARCHAR(100) NULL DEFAULT "0"');

-- --------------------------------------------------------------------
-- 6. Apply Schema Upgrades to Tenant History Table
-- --------------------------------------------------------------------

CALL AddColumnIfNotExists('tenant_history', 'elec_balance', 'VARCHAR(100) NULL DEFAULT "0"');
CALL AddColumnIfNotExists('tenant_history', 'water_balance', 'VARCHAR(100) NULL DEFAULT "0"');

-- --------------------------------------------------------------------
-- 7. Clean up Stored Procedure
-- --------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`;

SET FOREIGN_KEY_CHECKS = 1;

-- Migration Completed Successfully!
