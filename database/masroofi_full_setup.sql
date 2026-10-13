-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: masroofi_db
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('msrofy-cache-masroofi_exchange_rates_last_updated','s:25:\"2026-10-13T10:31:18+00:00\";',1791973878),('msrofy-cache-masroofi_exchange_rates_usd','a:9:{s:3:\"USD\";d:1;s:3:\"SAR\";d:3.75;s:3:\"ILS\";d:3.076893;s:3:\"AED\";d:3.6725;s:3:\"JOD\";d:0.709;s:3:\"EGP\";d:52.658715;s:3:\"KWD\";d:0.311015;s:3:\"QAR\";d:3.64;s:3:\"EUR\";d:0.895156;}',1791891078),('msrofy-pro-cache-admin@remal.com|127.0.0.1','i:1;',1791822365),('msrofy-pro-cache-admin@remal.com|127.0.0.1:timer','i:1791822365;',1791822365),('msrofy-pro-cache-masroofi_exchange_rates_last_updated','s:25:\"2026-10-13T09:23:19+00:00\";',1791969799),('msrofy-pro-cache-masroofi_exchange_rates_usd','a:9:{s:3:\"USD\";d:1;s:3:\"SAR\";d:3.75;s:3:\"ILS\";d:3.076893;s:3:\"AED\";d:3.6725;s:3:\"JOD\";d:0.709;s:3:\"EGP\";d:52.658715;s:3:\"KWD\";d:0.311015;s:3:\"QAR\";d:3.64;s:3:\"EUR\";d:0.895156;}',1791886999);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `debt_harvest_logs`
--

DROP TABLE IF EXISTS `debt_harvest_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `debt_harvest_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `debt_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `day_allowance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `day_spent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `surplus_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `harvested_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `debt_harvest_logs_debt_id_date_unique` (`debt_id`,`date`),
  KEY `debt_harvest_logs_workspace_id_index` (`workspace_id`),
  CONSTRAINT `debt_harvest_logs_debt_id_foreign` FOREIGN KEY (`debt_id`) REFERENCES `debts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `debt_harvest_logs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `debt_harvest_logs`
--

LOCK TABLES `debt_harvest_logs` WRITE;
/*!40000 ALTER TABLE `debt_harvest_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `debt_harvest_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `debt_records`
--

DROP TABLE IF EXISTS `debt_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `debt_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `debt_id` bigint unsigned NOT NULL,
  `type` enum('add_debt','repayment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'add_debt',
  `amount` decimal(12,2) NOT NULL,
  `date` date NOT NULL,
  `payment_method` enum('transfer','cash','card') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `debt_records_debt_id_foreign` (`debt_id`),
  KEY `debt_records_workspace_id_index` (`workspace_id`),
  KEY `debt_records_user_id_index` (`user_id`),
  CONSTRAINT `debt_records_debt_id_foreign` FOREIGN KEY (`debt_id`) REFERENCES `debts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `debt_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `debt_records_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `debt_records`
--

LOCK TABLES `debt_records` WRITE;
/*!40000 ALTER TABLE `debt_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `debt_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `debts`
--

DROP TABLE IF EXISTS `debts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `debts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supermarket',
  `icon` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F9B92,
  `total_debt` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_harvest_active` tinyint(1) NOT NULL DEFAULT '0',
  `harvest_ratio` decimal(5,2) NOT NULL DEFAULT '0.50',
  `harvest_max_daily` decimal(10,2) DEFAULT NULL,
  `surplus_harvested` decimal(12,2) NOT NULL DEFAULT '0.00',
  `harvest_started_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `debts_workspace_id_index` (`workspace_id`),
  KEY `debts_user_id_index` (`user_id`),
  CONSTRAINT `debts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `debts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `debts`
--

LOCK TABLES `debts` WRITE;
/*!40000 ALTER TABLE `debts` DISABLE KEYS */;
/*!40000 ALTER TABLE `debts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `envelopes`
--

DROP TABLE IF EXISTS `envelopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `envelopes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الظرف',
  `icon` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F93A6 COMMENT 'أيقونة الإيموجي',
  `budget` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'سقف الميزانية المخصصة',
  `pillar` enum('needs','wants','savings') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'needs' COMMENT 'التصنيف الأساسي',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#10b981' COMMENT 'اللون المميز للظرف',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `envelopes_workspace_id_index` (`workspace_id`),
  CONSTRAINT `envelopes_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `envelopes`
--

LOCK TABLES `envelopes` WRITE;
/*!40000 ALTER TABLE `envelopes` DISABLE KEYS */;
INSERT INTO `envelopes` VALUES (1,'سكن وفواتير','🏠',0.00,'needs','#3b82f6','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(2,'بقالة وتموين','🛒',0.00,'needs','#10b981','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(3,'بنزين ومواصلات','🚗',0.00,'needs','#06b6d4','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(4,'صحة وصيدلية','💊',0.00,'needs','#ec4899','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(5,'مطاعم وتوصيل','🍔',0.00,'wants','#f59e0b','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(6,'تسوق وملابس','🛍️',0.00,'wants','#8b5cf6','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(7,'ترفيه ومستلزمات','✈️',0.00,'wants','#f97316','2026-10-11 08:58:38','2026-10-12 12:25:06',1),(8,'تحويش للطوارئ','🛡️',0.00,'savings','#10b981','2026-10-11 08:58:38','2026-10-12 12:25:06',1);
/*!40000 ALTER TABLE `envelopes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_11_000001_create_masroofi_tables',1),(5,'2026_10_11_000002_ensure_updated_at_on_transactions_table',2),(6,'2026_10_11_000003_add_currency_to_saving_goals_table',2),(7,'2026_10_11_000004_create_salary_sources_table',3),(8,'2026_10_11_124459_add_cycle_mode_to_user_settings_table',4),(9,'2026_10_12_095848_create_debts_and_debt_records_tables',5),(10,'2026_10_12_103446_add_surplus_harvesting_to_debts_and_create_harvest_logs_table',6),(11,'2026_10_12_122417_create_workspaces_and_multi_user_system_table',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_sources`
--

DROP TABLE IF EXISTS `salary_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ILS',
  `payment_method` enum('transfer','cash','card') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transfer',
  `day_of_month` int NOT NULL DEFAULT '1',
  `expected_date` date DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_sources_workspace_id_index` (`workspace_id`),
  CONSTRAINT `salary_sources_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_sources`
--

LOCK TABLES `salary_sources` WRITE;
/*!40000 ALTER TABLE `salary_sources` DISABLE KEYS */;
INSERT INTO `salary_sources` VALUES (4,'مصدر دخل 11 / 10 /2026',1961.00,'ILS','transfer',11,'2026-09-19',NULL,1,'2026-10-11 09:54:21','2026-10-11 09:54:21',1),(5,'مصاري الكاش الشهور السابقة',1850.00,'ILS','cash',11,'2026-10-11',NULL,1,'2026-10-11 09:55:33','2026-10-11 10:05:44',1);
/*!40000 ALTER TABLE `salary_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saving_goals`
--

DROP TABLE IF EXISTS `saving_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saving_goals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الهدف أو الحصالة',
  `icon` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F8EAF COMMENT 'أيقونة الهدف',
  `target_amount` decimal(12,2) NOT NULL COMMENT 'المبلغ المستهدف',
  `current_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'المبلغ المجموع حالياً',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ILS',
  `deadline` date DEFAULT NULL COMMENT 'تاريخ الإنجاز المستهدف',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `saving_goals_workspace_id_index` (`workspace_id`),
  CONSTRAINT `saving_goals_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saving_goals`
--

LOCK TABLES `saving_goals` WRITE;
/*!40000 ALTER TABLE `saving_goals` DISABLE KEYS */;
INSERT INTO `saving_goals` VALUES (7,'صندوق الطوراىء بالدولار','🛡️',1100.00,1100.00,'USD','2026-10-12','2026-10-12 04:38:59','2026-10-12 12:25:06',1);
/*!40000 ALTER TABLE `saving_goals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('8EZBFmDOH0yc3UGhezv9k2sMzusd5nHU9JV66J45',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ0U3NpMjFiaGYxd0E0TkgwWEZkaVpmcHF5VWE1T3JhalB3Q0Ntd2lTIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3Q6ODAwMCIsInJvdXRlIjoiZGFzaGJvYXJkIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1791887478),('jATlfJ3CqSZYNFpX4OoncL76ZqJcjcBDsdW75KWB',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJBd0tjM1NpN3B4Z1VVZ09BVkVTa08wTXZsN0FmcmtqNGYwdEN1Mzc4IiwiX2ZsYXNoIjp7Im5ldyI6W10sIm9sZCI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDAiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MSwicGFzc3dvcmRfaGFzaF93ZWIiOiJkYjc1ZWE2N2I3Y2I2NzA0ZjA2NWI2NjhkY2IyNTEzZmU5NmU1OGRhNjFlZmM4ZjgzNjExMGJlNjkxNzZjN2NmIn0=',1791886904),('qZ1QgNfvvdH1a8kjRcfFmwYwuWHlZg1kZRNQrJom',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJxQmk1UVppVTlRam9jN2Y5emR2NXN4bVpoMjlkcnl1cDBLR1BEQjJiIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3Q6ODAwMFwvYWRtaW5cL3VzZXJzIiwicm91dGUiOiJhZG1pbi51c2Vycy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1791886239);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم الخدمة أو الاشتراك',
  `cost` decimal(12,2) NOT NULL COMMENT 'قيمة الاشتراك',
  `period` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT 'دورية التجديد',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_workspace_id_index` (`workspace_id`),
  CONSTRAINT `subscriptions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('expense','income','savings') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expense' COMMENT 'نوع الحركة',
  `amount` decimal(12,2) NOT NULL COMMENT 'المبلغ',
  `envelope_id` int DEFAULT NULL COMMENT 'معرف الظرف المرتبط',
  `goal_id` int DEFAULT NULL COMMENT 'معرف هدف التحويش المرتبط في حال الإيداع',
  `date` date NOT NULL COMMENT 'تاريخ المعاملة',
  `payment_method` enum('card','cash','transfer','credit') COLLATE utf8mb4_unicode_ci DEFAULT 'card' COMMENT 'طريقة الدفع',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'الملاحظة أو التفاصيل',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `envelope_id` (`envelope_id`),
  KEY `goal_id` (`goal_id`),
  KEY `transactions_workspace_id_index` (`workspace_id`),
  KEY `transactions_user_id_index` (`user_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`envelope_id`) REFERENCES `envelopes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`goal_id`) REFERENCES `saving_goals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (9,'expense',3.00,3,NULL,'2026-10-11','transfer','Updated transport','2026-10-12 04:35:23','2026-10-12 08:27:34',1,1),(10,'expense',3.00,3,NULL,'2026-10-12','transfer','مواصلات من الشركة ل المدفع','2026-10-13 02:49:26','2026-10-13 02:49:26',1,1),(11,'expense',4.00,2,NULL,'2026-10-12','transfer','حجات من بسطة محمود أخي','2026-10-13 02:49:26','2026-10-13 02:49:26',1,1),(13,'expense',15.00,4,7,'2026-10-13','transfer','دواء كونجستال لحمود','2026-10-13 05:42:08','2026-10-13 05:42:08',1,1),(14,'expense',20.00,2,7,'2026-10-12','transfer','حجات لسهرة','2026-10-13 06:54:38','2026-10-13 06:54:38',1,1);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `salary` decimal(12,2) NOT NULL DEFAULT '7500.00' COMMENT 'الراتب الشهري الصافي',
  `salary_day` int NOT NULL DEFAULT '27' COMMENT 'يوم نزول الراتب في الشهر',
  `cycle_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'first_salary',
  `work_hours_per_week` int NOT NULL DEFAULT '40' COMMENT 'ساعات العمل الأسبوعية',
  `savings_target_percent` int NOT NULL DEFAULT '20' COMMENT 'النسبة المستهدفة للادخار %',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SAR' COMMENT 'العملة المختارة',
  `theme` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dark' COMMENT 'المظهر (dark/light)',
  `sound_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'المؤثرات الصوتية',
  `saved_from_impulse` decimal(12,2) NOT NULL DEFAULT '280.00' COMMENT 'المبالغ الموفرة من النزوات',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workspace_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_settings_workspace_id_index` (`workspace_id`),
  CONSTRAINT `user_settings_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_settings`
--

LOCK TABLES `user_settings` WRITE;
/*!40000 ALTER TABLE `user_settings` DISABLE KEYS */;
INSERT INTO `user_settings` VALUES (1,3811.00,1,'custom_day',40,20,'ILS','dark',1,0.00,'2026-10-11 08:58:38','2026-10-13 05:41:01',1);
/*!40000 ALTER TABLE `user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pin_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `role` enum('admin','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `workspace_id` bigint unsigned DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `users_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Ahmed Jouda','ahmedjouda','joda.ahmad.96@gmail.com','059710138',NULL,'$2y$12$VysNDCid7mu3g6MUNY59w.gTtYKuMOGKS51YKRAw43IUlKkQUfSfa','$2y$12$8v38pq.4xDCGYqeRSBQGPeDBOKTZh8Ea0AKyE0BjcA4nxUtRJoYoe',1,'admin',1,NULL,'Lq55g3hsWlU2aGAOHyCwiypyAyrWncNB8iLmzOL2yOV8ur7aYHvdjvFRIP0M','2026-10-12 09:25:06','2026-10-13 07:21:38');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم السلعة',
  `price` decimal(12,2) NOT NULL COMMENT 'السعر التقديري',
  `duration_hours` int NOT NULL DEFAULT '48' COMMENT 'ساعات فترة التهدئة',
  `status` enum('pending','saved','bought') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wishlist_workspace_id_index` (`workspace_id`),
  KEY `wishlist_user_id_index` (`user_id`),
  CONSTRAINT `wishlist_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wishlist_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspaces`
--

DROP TABLE IF EXISTS `workspaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'المحفظة الرئيسية (العائلة)',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ILS',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspaces`
--

LOCK TABLES `workspaces` WRITE;
/*!40000 ALTER TABLE `workspaces` DISABLE KEYS */;
INSERT INTO `workspaces` VALUES (1,'المحفظة الرئيسية (العائلة)','ILS',NULL,'2026-10-12 09:25:06','2026-10-12 09:25:06');
/*!40000 ALTER TABLE `workspaces` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-13 13:55:19
