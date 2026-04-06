CREATE DATABASE  IF NOT EXISTS `laravel` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `laravel`;
-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: laravel
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_logs`
--

DROP TABLE IF EXISTS `access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `access_logs`
--

LOCK TABLES `access_logs` WRITE;
/*!40000 ALTER TABLE `access_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_types`
--

DROP TABLE IF EXISTS `admin_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_view_profits` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_admins` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_finances` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_types_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_types`
--

LOCK TABLES `admin_types` WRITE;
/*!40000 ALTER TABLE `admin_types` DISABLE KEYS */;
INSERT INTO `admin_types` VALUES (1,'full','Full Admin',0,0,0,'2025-10-15 13:44:05','2025-10-15 13:44:05'),(2,'partial','Partial Admin',0,0,0,'2025-10-15 13:44:05','2025-10-15 13:44:05');
/*!40000 ALTER TABLE `admin_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_submissions`
--

DROP TABLE IF EXISTS `assignment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignment_submissions` (
  `submission_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` int DEFAULT NULL,
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `graded_by` bigint unsigned DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `assignment_submissions_assignment_id_student_id_unique` (`assignment_id`,`student_id`),
  KEY `assignment_submissions_student_id_foreign` (`student_id`),
  KEY `assignment_submissions_graded_by_foreign` (`graded_by`),
  CONSTRAINT `assignment_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `assignment_submissions_graded_by_foreign` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `assignment_submissions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_submissions`
--

LOCK TABLES `assignment_submissions` WRITE;
/*!40000 ALTER TABLE `assignment_submissions` DISABLE KEYS */;
INSERT INTO `assignment_submissions` VALUES (1,1,1,'2024-09-08 11:30:00','submissions/assignment1_mike.pdf',85,'Good work, but show more detailed steps',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,1,2,'2024-09-07 13:45:00','submissions/assignment1_sara.pdf',92,'Excellent solutions with proper explanations',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,2,'2024-09-09 08:20:00','submissions/assignment2_sara.docx',88,'Well-structured report with good analysis',3,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `assignment_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `teacher_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `max_score` int NOT NULL DEFAULT '100',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `assignments_session_id_group_id_unique` (`session_id`,`group_id`),
  KEY `assignments_group_id_foreign` (`group_id`),
  KEY `assignments_created_by_foreign` (`created_by`),
  CONSTRAINT `assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `assignments_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,1,1,'Calculus Basics Assignment','Complete exercises 1-10 from chapter 1','assignments/calc_basics.pdf',NULL,'2024-09-09 23:59:00',100,2,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,3,'Physics Lab Report','Write a report on Newton laws experiment','assignments/physics_lab.docx',NULL,'2024-09-10 23:59:00',100,3,'2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `attendance_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `status` enum('present','absent','late','excused') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recorded_by` bigint unsigned NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `attendance_session_id_student_id_unique` (`session_id`,`student_id`),
  KEY `attendance_student_id_foreign` (`student_id`),
  KEY `attendance_recorded_by_foreign` (`recorded_by`),
  KEY `attendance_session_id_index` (`session_id`),
  CONSTRAINT `attendance_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `attendance_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`),
  CONSTRAINT `attendance_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,1,'present','Active participation',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,1,2,'present','Good engagement',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,1,'late','Arrived 15 minutes late',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,2,2,'present','Excellent performance',2,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(5,3,2,'present','Demonstrated good understanding',3,'2025-10-12 23:19:20','2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
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
-- Table structure for table `certificate_requests`
--

DROP TABLE IF EXISTS `certificate_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `certificate_requests_user_id_foreign` (`user_id`),
  CONSTRAINT `certificate_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_requests`
--

LOCK TABLES `certificate_requests` WRITE;
/*!40000 ALTER TABLE `certificate_requests` DISABLE KEYS */;
INSERT INTO `certificate_requests` VALUES (1,4,1,1,'approved','Completed all requirements successfully','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,5,2,2,'approved','Awaiting final assessment','2025-10-12 23:19:20','2025-10-12 20:49:21');
/*!40000 ALTER TABLE `certificate_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_templates`
--

DROP TABLE IF EXISTS `certificate_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `html` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `blade_view` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `font_style` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seal_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_templates`
--

LOCK TABLES `certificate_templates` WRITE;
/*!40000 ALTER TABLE `certificate_templates` DISABLE KEYS */;
INSERT INTO `certificate_templates` VALUES (1,'Standard Certificate','<div class=\"certificate\"><h1>Certificate of Completion</h1></div>','certificates.standard','templates/standard_bg.jpg','Arial','#000000','templates/signature.png','templates/seal.png',1,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'Honors Certificate','<div class=\"certificate honors\"><h1>Certificate of Excellence</h1></div>','certificates.honors','templates/honors_bg.jpg','Times New Roman','#2C3E50','templates/signature.png','templates/seal.png',1,'2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `certificate_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `certificate_type` enum('individual','group_completion') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `course_id` bigint unsigned DEFAULT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `certificate_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `quiz_average` decimal(5,2) DEFAULT NULL,
  `final_rating` decimal(5,2) DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('issued','revoked','draft') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `instructor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `certificates_user_id_foreign` (`user_id`),
  KEY `certificates_template_id_foreign` (`template_id`),
  CONSTRAINT `certificates_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `certificate_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,4,'group_completion',1,1,1,1,'CERT-2024-001','2024-09-15',95.00,88.00,90.50,'certificates/cert_2024_001.pdf','issued',NULL,'Outstanding performance in mathematics','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,4,'individual',1,1,NULL,1,'CERT-VH4KUB3J','2025-10-13',50.00,NULL,8.50,'certificates/CERT-VH4KUB3J.pdf','issued',NULL,'test','2025-10-12 20:45:33','2025-10-12 20:47:11'),(3,5,'group_completion',1,1,NULL,1,'CERT-UWUVF7IF','2025-10-12',100.00,NULL,NULL,'certificates/CERT-UWUVF7IF.pdf','issued',NULL,NULL,'2025-10-12 20:47:39','2025-10-12 20:47:39'),(4,5,'group_completion',1,1,NULL,1,'CERT-UPR2NIBJ','2025-10-12',100.00,NULL,NULL,'certificates/CERT-UPR2NIBJ.pdf','issued',NULL,NULL,'2025-10-12 20:48:23','2025-10-12 20:48:23'),(5,5,'individual',1,1,NULL,1,'CERT-FTVTLGPX','2025-10-30',100.00,NULL,9.00,'certificates/CERT-FTVTLGPX.pdf','issued',NULL,'فثسف','2025-10-12 20:48:44','2025-10-12 20:48:48'),(6,5,'individual',2,2,NULL,1,'CERT-SOCOTKKA','2025-10-12',NULL,NULL,NULL,'certificates/CERT-SOCOTKKA.pdf','issued',NULL,'Awaiting final assessment','2025-10-12 20:49:21','2025-10-12 20:49:31'),(7,4,'individual',1,1,NULL,1,'CERT-4RXASVUU','2025-10-12',50.00,NULL,8.50,'certificates/CERT-4RXASVUU.pdf','issued',NULL,'نع','2025-10-12 20:50:17','2025-10-12 20:50:29'),(8,5,'group_completion',1,1,NULL,1,'CERT-YMASPM5X','2025-10-13',100.00,NULL,9.00,NULL,'draft',NULL,NULL,'2025-10-13 19:38:57','2025-10-13 19:38:57'),(9,8,'group_completion',1,1,NULL,1,'CERT-3ESSFD8A','2025-10-13',0.00,NULL,NULL,NULL,'issued',NULL,'he is the first in quiz','2025-10-13 19:40:30','2025-10-13 19:40:52'),(10,5,'individual',NULL,NULL,NULL,1,'CERT-RCARZ6GR','2025-10-14',NULL,NULL,NULL,NULL,'issued',NULL,'test','2025-10-13 19:47:22','2025-10-13 19:47:31'),(11,4,'individual',NULL,1,NULL,1,'CERT-MGQN2DPF','2025-10-13',50.00,NULL,8.50,NULL,'issued',NULL,'for passingthe test','2025-10-13 19:50:21','2025-10-13 19:50:29'),(12,4,'individual',NULL,1,NULL,1,'CERT-O1UCY6BP','2025-10-14',50.00,NULL,8.50,NULL,'issued',NULL,'test','2025-10-13 19:52:22','2025-10-13 19:53:01'),(13,4,'individual',NULL,1,NULL,1,'CERT-VWKCQRBA','2025-10-13',50.00,NULL,8.50,NULL,'issued',NULL,'test test','2025-10-13 19:54:04','2025-10-13 19:54:18'),(14,5,'group_completion',1,1,NULL,1,'CERT-N626O6FP','2025-10-13',100.00,NULL,9.00,NULL,'draft',NULL,NULL,'2025-10-13 20:13:49','2025-10-13 20:13:49'),(15,4,'individual',NULL,1,NULL,2,'CERT-XCGS80LP','2025-10-14',50.00,NULL,8.50,NULL,'issued',NULL,'eeg','2025-10-13 20:25:01','2025-10-13 20:25:07'),(16,5,'group_completion',1,1,NULL,2,'CERT-QACKNNLT','2025-10-13',100.00,NULL,9.00,'certificates/CERT-QACKNNLT.pdf','issued','Professor Smith',NULL,'2025-10-13 20:36:35','2025-10-13 20:36:36'),(17,4,'individual',1,1,NULL,2,'BADGE-PAGFSHJO','2025-10-14',NULL,NULL,NULL,NULL,'issued',NULL,'test','2025-10-13 21:13:52','2025-10-13 21:13:52');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_students`
--

DROP TABLE IF EXISTS `course_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_students_course_id_student_id_unique` (`course_id`,`student_id`),
  KEY `course_students_student_id_foreign` (`student_id`),
  CONSTRAINT `course_students_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `course_students_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_students`
--

LOCK TABLES `course_students` WRITE;
/*!40000 ALTER TABLE `course_students` DISABLE KEYS */;
INSERT INTO `course_students` VALUES (1,1,1,'2024-08-31 21:00:00','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,2,'2024-09-01 21:00:00','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,1,2,'2024-08-31 21:00:00','2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `course_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `course_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `department_id` bigint unsigned DEFAULT NULL,
  `teacher_id` bigint DEFAULT NULL,
  `schedule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`course_id`),
  KEY `courses_department_id_foreign` (`department_id`),
  KEY `courses_teacher_id_foreign` (`teacher_id`),
  CONSTRAINT `courses_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'Advanced Mathematics','Comprehensive course covering advanced mathematical concepts and applications',1,1,'Mon-Wed-Fri 10:00-12:00','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'Physics Fundamentals','Introduction to fundamental physics principles and laboratory work',2,2,'Tue-Thu 14:00-16:00','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,'Intro to PHP',NULL,NULL,NULL,NULL,NULL,NULL),(4,'Advanced Laravel',NULL,NULL,NULL,NULL,NULL,NULL),(5,'test',NULL,1,1,'sunday  12 to 2',NULL,NULL),(6,'Intro to PHP1',NULL,NULL,NULL,NULL,NULL,NULL),(7,'Advanced Laravel1',NULL,NULL,NULL,NULL,NULL,NULL),(8,'Intro to PHP2','Basic PHP course',NULL,1,NULL,'2025-10-13 22:15:54','2025-10-13 22:15:54'),(9,'Advanced Laravel2','Deep dive into Laravel',NULL,2,NULL,'2025-10-13 22:15:54','2025-10-13 22:15:54');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `department`
--

DROP TABLE IF EXISTS `department`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `department` (
  `department_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `department_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `head_teacher_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `department`
--

LOCK TABLES `department` WRITE;
/*!40000 ALTER TABLE `department` DISABLE KEYS */;
INSERT INTO `department` VALUES (1,'Mathematics','Mathematics and Advanced Calculus Department',2,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'Science','Science and Research Department',3,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,'Computer Science','Computer Science and Programming',NULL,'2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `department` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `expense_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` enum('consumables','rent','utilities','equipment','marketing','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `recorded_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`expense_id`),
  KEY `expenses_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `expenses_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'equipment','Purchase of new projectors for classrooms',2500.00,'2024-09-01',6,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'consumables','Textbooks and learning materials',800.00,'2024-09-05',6,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,'utilities','Monthly electricity and internet bills',450.00,'2024-09-10',6,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,'other','test',80000.00,'2025-10-13',1,NULL,NULL);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
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
-- Table structure for table `group_messages`
--

DROP TABLE IF EXISTS `group_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_messages`
--

LOCK TABLES `group_messages` WRITE;
/*!40000 ALTER TABLE `group_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_user`
--

DROP TABLE IF EXISTS `group_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_user`
--

LOCK TABLES `group_user` WRITE;
/*!40000 ALTER TABLE `group_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `subcourse_id` bigint unsigned DEFAULT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `schedule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `teacher_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  KEY `groups_course_id_foreign` (`course_id`),
  KEY `groups_subcourse_id_foreign` (`subcourse_id`),
  KEY `groups_teacher_id_foreign` (`teacher_id`),
  CONSTRAINT `groups_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  CONSTRAINT `groups_subcourse_id_foreign` FOREIGN KEY (`subcourse_id`) REFERENCES `subcourses` (`subcourse_id`),
  CONSTRAINT `groups_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,'Math Group A',1,1,1,'Mon-Wed-Fri 10:00-12:00','2024-09-01','2024-12-20',1500.00,0.00,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'Science Group B',2,3,2,'Tue-Thu 14:00-16:00','2024-09-02','2024-12-22',1600.00,0.00,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,'Advanced Laravel1',4,NULL,1,'Mon-Wed 6-8pm','2025-10-01','2025-12-31',1500.00,0.00,NULL,NULL),(4,'Intro to PHP1',5,NULL,2,'Tue-Thu 5-7pm','2025-11-01','2026-01-31',1200.00,0.00,NULL,NULL),(5,'test percentage',2,3,2,'sunday  12 to 2','2024-10-27','2025-11-25',500.00,20.00,NULL,NULL),(6,'ththth',1,NULL,1,'sunday  12 to 2','2025-10-08','2025-11-07',1000.00,20.00,NULL,NULL),(7,'gge',6,NULL,2,'sunday  12 to 2','2025-10-14','2025-10-14',900.00,20.00,NULL,NULL),(8,'test 222',8,NULL,2,'sunday  12 to 2','2025-04-28','2026-02-24',800.00,20.00,NULL,NULL),(9,'[p;lokiju',6,NULL,4,'sunday  12 to 2','2025-06-29','2026-01-21',300.00,20.00,NULL,NULL),(10,'`sdrftvgybhjmk',2,4,4,'sunday  12 to 2','2025-10-14','2026-04-01',700.00,20.00,NULL,NULL),(11,'jkhgj',3,NULL,4,'tt','2025-12-23','2025-11-02',250.00,30.00,NULL,NULL),(12,'esrtyuiop',8,NULL,1,'sunday  12 to 2','2025-10-14','2026-01-13',650.00,50.00,NULL,NULL),(13,'Advanced Laravel155',7,NULL,1,'Mon-Wed 6-8pm','2025-10-01','2025-12-31',1500.00,70.00,NULL,NULL),(14,'new upload test',1,NULL,2,'Tue-Thu 5-7pm','2025-11-01','2026-01-31',1200.00,30.00,NULL,NULL);
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_logs`
--

DROP TABLE IF EXISTS `import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `imported_by` bigint unsigned NOT NULL,
  `imported_at` timestamp NOT NULL,
  `success_count` int NOT NULL DEFAULT '0',
  `failed_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_logs_imported_by_foreign` (`imported_by`),
  CONSTRAINT `import_logs_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_logs`
--

LOCK TABLES `import_logs` WRITE;
/*!40000 ALTER TABLE `import_logs` DISABLE KEYS */;
INSERT INTO `import_logs` VALUES (1,'users_example (1).csv',1,'2025-10-13 15:55:46',2,0,'2025-10-13 15:55:46','2025-10-13 15:55:46'),(2,'courses_example.csv',1,'2025-10-13 22:02:39',2,0,'2025-10-13 22:02:39','2025-10-13 22:02:39'),(3,'courses_example.csv',1,'2025-10-13 22:11:40',2,0,'2025-10-13 22:11:40','2025-10-13 22:11:40'),(4,'courses_example.csv',1,'2025-10-13 22:15:54',2,0,'2025-10-13 22:15:54','2025-10-13 22:15:54'),(5,'groups_example.csv',1,'2025-10-13 22:39:13',2,0,'2025-10-13 22:39:13','2025-10-13 22:39:13'),(6,'groups_example (1).csv',1,'2025-10-14 18:23:45',2,0,'2025-10-14 18:23:45','2025-10-14 18:23:45'),(7,'users_example (2).csv',1,'2025-10-15 12:55:48',1,0,'2025-10-15 12:55:48','2025-10-15 12:55:48');
/*!40000 ALTER TABLE `import_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `invoice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','partial','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_student_id_foreign` (`student_id`),
  KEY `invoices_group_id_foreign` (`group_id`),
  CONSTRAINT `invoices_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`),
  CONSTRAINT `invoices_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,1,'INV-2024-001','Tuition fee for Math Group A - Semester 1',1500.00,1500.00,'paid','2024-09-30','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,2,'INV-2024-002','Tuition fee for Science Group B - Semester 1',1600.00,1600.00,'paid','2024-09-30','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,1,'INV-2024-003','Additional course fee for Math Group A',1500.00,1500.00,'paid','2024-10-15','2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,1,8,'INV-20251014-2269','Group fee: test 222',800.00,800.00,'paid','2025-04-28',NULL,NULL),(5,2,8,'INV-20251014-7708','Group fee: test 222',800.00,800.00,'paid','2025-04-28',NULL,NULL),(6,3,8,'INV-20251014-4157','Group fee: test 222',800.00,800.00,'paid','2025-04-28',NULL,NULL),(7,1,9,'INV-20251014-2547','Group fee: [p;lokiju',300.00,300.00,'paid','2025-06-29',NULL,NULL),(8,2,9,'INV-20251014-9475','Group fee: [p;lokiju',300.00,300.00,'paid','2025-06-29',NULL,NULL),(9,3,9,'INV-20251014-7445','Group fee: [p;lokiju',300.00,300.00,'paid','2025-06-29',NULL,NULL),(10,1,10,'INV-20251014-3218','Group fee: `sdrftvgybhjmk',700.00,700.00,'paid','2025-10-14',NULL,NULL),(11,2,10,'INV-20251014-8571','Group fee: `sdrftvgybhjmk',700.00,700.00,'paid','2025-10-14',NULL,NULL),(12,3,10,'INV-20251014-6810','Group fee: `sdrftvgybhjmk',700.00,700.00,'paid','2025-10-14',NULL,NULL),(13,1,11,'INV-20251014-5070','Group fee: jkhgj',250.00,250.00,'paid','2025-12-23',NULL,NULL),(14,2,11,'INV-20251014-3855','Group fee: jkhgj',250.00,250.00,'paid','2025-12-23',NULL,NULL),(15,3,11,'INV-20251014-3085','Group fee: jkhgj',250.00,250.00,'paid','2025-12-23',NULL,NULL),(16,1,12,'INV-20251014-5962','Group fee: esrtyuiop',650.00,650.00,'paid','2025-10-14',NULL,NULL),(17,2,12,'INV-20251014-1865','Group fee: esrtyuiop',650.00,650.00,'paid','2025-10-14',NULL,NULL),(18,3,12,'INV-20251014-9828','Group fee: esrtyuiop',650.00,650.00,'paid','2025-10-14',NULL,NULL),(19,1,13,'INV-20251014-6977','Group fee: Advanced Laravel155',1500.00,1500.00,'paid','2025-10-01',NULL,NULL),(20,2,13,'INV-20251014-6799','Group fee: Advanced Laravel155',1500.00,1500.00,'paid','2025-10-01',NULL,NULL),(21,3,13,'INV-20251014-3427','Group fee: Advanced Laravel155',1500.00,1500.00,'paid','2025-10-01',NULL,NULL),(22,2,14,'INV-20251014-4510','Group fee: new upload test',1200.00,1200.00,'paid','2025-11-01',NULL,NULL),(23,3,14,'INV-20251014-3228','Group fee: new upload test',1200.00,1200.00,'paid','2025-11-01',NULL,NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
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
-- Table structure for table `laravel_sessions`
--

DROP TABLE IF EXISTS `laravel_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laravel_sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `laravel_sessions_user_id_index` (`user_id`),
  KEY `laravel_sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laravel_sessions`
--

LOCK TABLES `laravel_sessions` WRITE;
/*!40000 ALTER TABLE `laravel_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `laravel_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000001_create_cache_table',1),(2,'0001_01_01_000002_create_jobs_table',1),(3,'2014_10_12_100000_create_password_resets_table',1),(4,'2025_09_04_105719_0001_create_roles_table',1),(5,'2025_09_04_105720_0002_create_department_table',1),(6,'2025_09_04_105721_0003_create_users_table',1),(7,'2025_09_04_105722_0004_create_profile_table',1),(8,'2025_09_04_105723_0005_create_teachers_table',1),(9,'2025_09_04_105724_0006_create_students_table',1),(10,'2025_09_04_105725_0007_create_courses_table',1),(11,'2025_09_04_105726_0008_create_subcourses_table',1),(12,'2025_09_04_105727_0009_create_groups_table',1),(13,'2025_09_04_105728_0010_create_student_course_table',1),(14,'2025_09_04_105729_0011_create_student_group_table',1),(15,'2025_09_04_105729_0012_create_sessions_table',1),(16,'2025_09_04_105729_0013_create_laravel_sessions_table',1),(17,'2025_09_04_105730_0013_create_assignments_table',1),(18,'2025_09_04_105731_0014_create_assignment_submissions_table',1),(19,'2025_09_04_105732_0015_create_attendance_table',1),(20,'2025_09_04_105733_0016_create_ratings_table',1),(21,'2025_09_04_105734_0017_create_quizzes_table',1),(22,'2025_09_04_105735_0018_create_questions_table',1),(23,'2025_09_04_105736_0019_create_options_table',1),(24,'2025_09_04_105737_0020_create_quiz_attempts_table',1),(25,'2025_09_04_105737_0021_create_quiz_answers_table',1),(26,'2025_09_04_105738_0022_create_invoices_table',1),(27,'2025_09_04_105739_0023_create_payments_table',1),(28,'2025_09_04_105740_0024_create_salaries_table',1),(29,'2025_09_04_105741_0025_create_teacher_payments_table',1),(30,'2025_09_04_105742_0026_create_expenses_table',1),(31,'2025_09_04_105743_0027_create_notifications_table',1),(32,'2025_09_04_105815_0028_create_course_students_table',1),(33,'2025_09_14_000000_add_remember_token_to_users_table',1),(34,'2025_09_24_000000_add_updated_at_to_notifications_table',1),(35,'2025_09_24_172606_add_teacher_id_to_ratings_table',2),(36,'2025_09_28_100000_fix_sessions_timestamps',3),(37,'2025_10_08_221849_create_import_logs_table',3),(38,'2025_10_09_091010_create_group_messages_table',3),(39,'2025_10_11_000001_create_certificate_templates_table',3),(40,'2025_10_11_000001_create_session_materials_table',3),(41,'2025_10_12_000001_add_draft_status_to_certificates',4),(42,'2025_10_12_000002_add_html_to_certificate_templates',4),(43,'2025_10_12_000010_add_blade_view_to_certificate_templates',4),(44,'2025_10_12_000011_add_template_id_to_certificates',4),(45,'2025_10_14_000001_add_instructor_name_to_certificates_table',5),(46,'2025_10_14_000001_add_teacher_percentage_to_groups_table',6),(47,'2025_10_15_000001_normalize_password_resets_table',7),(48,'99992024_10_01_000000_create_teacher_adjustments_table',8),(49,'999999992025_09_28_095000_add_timestamps_to_sessions_table',9),(50,'999999992025_10_12_222123_add_certificate_type_to_certificates_table',10),(51,'9999999992025_10_11_000002_create_certificate_requests_table',11),(52,'99999999992025_10_11_000003_create_certificates_table',12),(53,'999999999992025_10_11_000004_create_group_user_table',13),(54,'9999999999999999999992025_09_24_000001_add_timestamps_to_expenses_table',14),(55,'2025_10_15_000002_expand_remember_token_length',15),(56,'2025_10_15_000001_create_admin_types_table',16),(57,'2025_10_15_000010_add_welcome_sent_to_users_table',17),(58,'2025_10_15_000002_create_admin_permissions_table',18),(59,'2025_10_15_000003_create_admin_permission_user_table',18),(60,'2025_10_15_000004_create_access_logs_table',19),(61,'2025_10_15_000005_add_is_full_only_to_admin_permissions_table',20),(62,'2025_10_15_000006_move_permissions_to_admin_types_and_drop_tables',21);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'Assignment Graded','Your Calculus Basics Assignment has been graded. Score: 85/100','assignment',1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,5,'Payment Reminder','Reminder: Partial payment pending for invoice INV-2024-002','payment',2,0,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,'New Session Scheduled','New session scheduled for Math Group A on 2024-09-06','session',3,1,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,9,'Salary Payment','Your salary for October 2025 has been paid. Amount: -720.00 EGP','salary',4,0,'2025-10-14 15:13:32',NULL),(5,9,'Salary Payment','Your salary for October 2025 has been paid. Amount: 0.00 EGP','salary',5,0,'2025-10-14 15:23:14',NULL),(6,9,'Salary Payment','Your salary for October 2025 has been paid. Amount: 0.00 EGP','salary',6,0,'2025-10-14 15:34:54',NULL),(7,3,'Salary Payment','Your salary for October 2025 has been paid. Amount: 480.00 EGP','salary',3,0,'2025-10-14 15:39:37',NULL),(8,3,'Salary Payment','Your salary for September 2024 has been paid. Amount: 1,200.00 EGP','salary',2,0,'2025-10-14 15:45:38',NULL),(9,2,'Salary Payment','Your salary for October 2025 has been paid. Amount: 975.00 EGP','salary',7,0,'2025-10-14 15:54:12',NULL);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `options` (
  `option_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `option_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  KEY `options_question_id_foreign` (`question_id`),
  CONSTRAINT `options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `options`
--

LOCK TABLES `options` WRITE;
/*!40000 ALTER TABLE `options` DISABLE KEYS */;
/*!40000 ALTER TABLE `options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
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
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_confirmed_by_foreign` (`confirmed_by`),
  CONSTRAINT `payments_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,1500.00,'bank_transfer','2024-09-05 07:30:00',1,'Full payment received','receipts/payment1.jpg',1,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,800.00,'vodafone_cash','2024-09-10 11:15:00',6,'Partial payment - first installment','receipts/payment2.jpg',1,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,15,250.00,'Cash','2025-10-14 18:39:00',1,NULL,NULL,1,NULL,NULL),(4,3,1500.00,'Bank Transfer','2025-10-14 18:51:51',1,NULL,NULL,1,NULL,NULL),(5,16,650.00,'Cash','2025-10-14 20:11:34',1,NULL,NULL,1,NULL,NULL),(6,2,800.00,'Cash','2025-10-14 20:11:54',1,NULL,NULL,1,NULL,NULL),(7,4,800.00,'Vodafone Cash','2025-10-14 20:12:52',1,NULL,NULL,1,NULL,NULL),(8,5,800.00,'Bank Transfer','2025-10-14 20:13:13',1,NULL,NULL,1,NULL,NULL),(9,6,800.00,'Cash','2025-10-14 20:13:40',1,NULL,NULL,1,NULL,NULL),(10,7,300.00,'Vodafone Cash','2025-10-14 20:13:57',1,NULL,NULL,1,NULL,NULL),(11,8,300.00,'Bank Transfer','2025-10-14 20:14:13',1,NULL,NULL,1,NULL,NULL),(12,9,300.00,'Bank Transfer','2025-10-14 20:14:35',1,NULL,NULL,1,NULL,NULL),(13,14,250.00,'Bank Transfer','2025-10-14 20:14:56',1,NULL,NULL,1,NULL,NULL),(14,13,250.00,'InstaPay','2025-10-14 20:15:13',1,NULL,NULL,1,NULL,NULL),(15,10,700.00,'Bank Transfer','2025-10-14 20:15:30',1,NULL,NULL,1,NULL,NULL),(16,11,700.00,'Cash','2025-10-14 20:15:48',1,NULL,NULL,1,NULL,NULL),(17,12,700.00,'Vodafone Cash','2025-10-14 20:16:04',1,NULL,NULL,1,NULL,NULL),(18,17,650.00,'Vodafone Cash','2025-10-14 20:16:23',1,NULL,NULL,1,NULL,NULL),(19,18,650.00,'Cash','2025-10-14 20:16:40',1,NULL,NULL,1,NULL,NULL),(20,19,1500.00,'admin_marked_paid','2025-10-16 18:14:51',1,NULL,NULL,0,NULL,NULL),(21,20,1500.00,'admin_marked_paid','2025-10-16 18:14:51',1,NULL,NULL,0,NULL,NULL),(22,21,1500.00,'admin_marked_paid','2025-10-16 18:14:51',1,NULL,NULL,0,NULL,NULL),(23,22,1200.00,'admin_marked_paid','2025-10-16 18:14:51',1,NULL,NULL,0,NULL,NULL),(24,23,1200.00,'admin_marked_paid','2025-10-16 18:14:51',1,NULL,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profile`
--

DROP TABLE IF EXISTS `profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile` (
  `profile_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `nickname` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `profile_picture_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `profile_user_id_unique` (`user_id`),
  CONSTRAINT `profile_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile`
--

LOCK TABLES `profile` WRITE;
/*!40000 ALTER TABLE `profile` DISABLE KEYS */;
INSERT INTO `profile` VALUES (1,1,'omar tolba','1980-05-15','01019522345','123 Admin Street, City','https://7049767c2f0e.ngrok-free.app/storage/profile_pictures/1760543290_1.png','2025-10-12 23:19:20','2025-10-15 12:48:10'),(2,2,'Prof','1975-08-20','+1234567891','456 Math Avenue, City','https://616e2a71781b.ngrok-free.app/storage/profile_pictures/1760402292_2.png','2025-10-12 23:19:20','2025-10-13 21:38:12'),(3,3,'Dr. Jones','1982-03-10','+1234567892','789 Science Road, City','uploads/profiles/dr_jones.jpg','2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,4,'Mike','2000-11-25','+1234567893','321 Student Lane, City','uploads/profiles/student_mike.jpg','2025-10-12 23:19:20','2025-10-12 23:19:20'),(5,5,'Sara','2001-02-14','+1234567894','654 Learning Blvd, City','uploads/profiles/student_sara.jpg','2025-10-12 23:19:20','2025-10-12 23:19:20'),(6,6,'Leo','1978-12-30','+1234567895','987 Finance Street, City','uploads/profiles/finance_leo.jpg','2025-10-12 23:19:20','2025-10-12 23:19:20'),(7,7,'John Donnge','1990-01-01','123457000000',NULL,NULL,'2025-10-13 15:55:46','2025-10-13 15:55:46'),(8,8,'Jangnne Smith','1992-05-15','9876554321',NULL,NULL,'2025-10-13 15:55:46','2025-10-13 15:55:46'),(9,9,'teacher test','2025-10-14','8987451548','ddf',NULL,'2025-10-14 15:11:20','2025-10-14 15:11:20'),(10,10,'ffrfrff','1990-01-01','1234567890',NULL,NULL,'2025-10-15 12:55:48','2025-10-15 12:55:48'),(11,11,'accountant','2025-10-15','588','rgrg',NULL,'2025-10-15 12:56:40','2025-10-15 13:11:57');
/*!40000 ALTER TABLE `profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `question_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint unsigned NOT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` enum('single_choice','multiple_choice','true_false','short_answer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int NOT NULL DEFAULT '1',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `questions_quiz_id_foreign` (`quiz_id`),
  CONSTRAINT `questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_answers`
--

DROP TABLE IF EXISTS `quiz_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_answers` (
  `answer_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `option_id` bigint unsigned DEFAULT NULL,
  `answer_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `quiz_answers_attempt_id_foreign` (`attempt_id`),
  KEY `quiz_answers_question_id_foreign` (`question_id`),
  KEY `quiz_answers_option_id_foreign` (`option_id`),
  CONSTRAINT `quiz_answers_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`attempt_id`),
  CONSTRAINT `quiz_answers_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `options` (`option_id`),
  CONSTRAINT `quiz_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_answers`
--

LOCK TABLES `quiz_answers` WRITE;
/*!40000 ALTER TABLE `quiz_answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempts` (
  `attempt_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed','graded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `quiz_attempts_quiz_id_foreign` (`quiz_id`),
  KEY `quiz_attempts_student_id_foreign` (`student_id`),
  CONSTRAINT `quiz_attempts_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`),
  CONSTRAINT `quiz_attempts_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_attempts`
--

LOCK TABLES `quiz_attempts` WRITE;
/*!40000 ALTER TABLE `quiz_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quizzes` (
  `quiz_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `time_limit` int DEFAULT NULL COMMENT 'الزمن بالدقائق',
  `max_attempts` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`quiz_id`),
  KEY `quizzes_session_id_foreign` (`session_id`),
  KEY `quizzes_created_by_foreign` (`created_by`),
  CONSTRAINT `quizzes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `quizzes_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quizzes`
--

LOCK TABLES `quizzes` WRITE;
/*!40000 ALTER TABLE `quizzes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ratings` (
  `rating_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `group_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned DEFAULT NULL,
  `session_id` bigint unsigned DEFAULT NULL,
  `rating_value` decimal(3,1) NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rated_by` bigint unsigned NOT NULL,
  `rated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rating_type` enum('assignment','session','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `month` int DEFAULT NULL,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`rating_id`),
  KEY `ratings_group_id_foreign` (`group_id`),
  KEY `ratings_rated_by_foreign` (`rated_by`),
  KEY `ratings_session_id_rating_type_index` (`session_id`,`rating_type`),
  KEY `ratings_student_id_session_id_index` (`student_id`,`session_id`),
  CONSTRAINT `ratings_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`),
  CONSTRAINT `ratings_rated_by_foreign` FOREIGN KEY (`rated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ratings_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`),
  CONSTRAINT `ratings_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ratings`
--

LOCK TABLES `ratings` WRITE;
/*!40000 ALTER TABLE `ratings` DISABLE KEYS */;
INSERT INTO `ratings` VALUES (1,1,1,1,1,8.5,'Good participation and understanding',2,'2025-10-12 23:19:20','session',9,2024,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,1,1,1,9.0,'Excellent performance and engagement',2,'2025-10-12 23:19:20','session',9,2024,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,2,2,3,8.0,'Good practical skills demonstration',3,'2025-10-12 23:19:20','session',9,2024,'2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `idroles` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idroles`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','System Administrator','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,'teacher','Course Instructor','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,'student','Registered Student','2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,'accountant','Financial Manager','2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salaries`
--

DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salaries` (
  `salary_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `group_revenue` decimal(10,2) NOT NULL DEFAULT '0.00',
  `teacher_share` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bonuses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`salary_id`),
  KEY `salaries_teacher_id_foreign` (`teacher_id`),
  KEY `salaries_group_id_foreign` (`group_id`),
  KEY `salaries_updated_by_foreign` (`updated_by`),
  CONSTRAINT `salaries_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`),
  CONSTRAINT `salaries_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  CONSTRAINT `salaries_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salaries`
--

LOCK TABLES `salaries` WRITE;
/*!40000 ALTER TABLE `salaries` DISABLE KEYS */;
INSERT INTO `salaries` VALUES (1,1,'2024-09',1,3000.00,2400.00,0.00,100.00,2500.00,'paid','2024-10-05',6,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,'2024-09',2,1600.00,1200.00,0.00,0.00,1200.00,'paid','2025-10-14',1,'2025-10-12 23:19:20','2025-10-14 15:45:38'),(3,2,'2025-10',8,2400.00,480.00,0.00,0.00,480.00,'paid','2025-10-14',1,'2025-10-14 15:09:05','2025-10-14 15:39:37'),(4,4,'2025-10',9,900.00,180.00,900.00,0.00,-720.00,'paid','2025-10-14',1,'2025-10-14 15:12:16','2025-10-14 15:13:32'),(5,4,'2025-10',10,2100.00,420.00,420.00,0.00,0.00,'paid','2025-10-14',1,'2025-10-14 15:21:49','2025-10-14 15:23:14'),(6,4,'2025-10',11,750.00,225.00,225.00,0.00,0.00,'paid','2025-10-14',1,'2025-10-14 15:33:30','2025-10-14 15:34:54'),(7,1,'2025-10',12,1950.00,975.00,0.00,0.00,975.00,'paid','2025-10-14',1,'2025-10-14 15:53:09','2025-10-14 15:54:12'),(8,1,'2025-10',13,4500.00,3150.00,0.00,0.00,3150.00,'pending',NULL,1,'2025-10-14 18:23:45','2025-10-14 18:23:45'),(9,2,'2025-10',14,2400.00,720.00,0.00,0.00,720.00,'pending',NULL,1,'2025-10-14 18:23:45','2025-10-14 18:23:45');
/*!40000 ALTER TABLE `salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `session_materials`
--

DROP TABLE IF EXISTS `session_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_materials_session_id_foreign` (`session_id`),
  KEY `session_materials_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `session_materials_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `session_materials_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `session_materials`
--

LOCK TABLES `session_materials` WRITE;
/*!40000 ALTER TABLE `session_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `session_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `session_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `topic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `sessions_group_id_foreign` (`group_id`),
  KEY `sessions_created_by_foreign` (`created_by`),
  CONSTRAINT `sessions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sessions_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES (1,1,'2024-09-02','10:00:00','12:00:00','Introduction to Calculus','First session covering basic concepts',2,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,1,'2024-09-04','10:00:00','12:00:00','Limits and Continuity','Understanding limits and function behavior',2,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,'2024-09-03','14:00:00','16:00:00','Newton Laws of Motion','Fundamental laws governing motion',3,'2025-10-12 23:19:20','2025-10-12 23:19:20');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_course`
--

DROP TABLE IF EXISTS `student_course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_course` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_course_student_id_course_id_unique` (`student_id`,`course_id`),
  KEY `student_course_course_id_foreign` (`course_id`),
  CONSTRAINT `student_course_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  CONSTRAINT `student_course_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_course`
--

LOCK TABLES `student_course` WRITE;
/*!40000 ALTER TABLE `student_course` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_group`
--

DROP TABLE IF EXISTS `student_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `group_id` bigint unsigned NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_group_student_id_group_id_unique` (`student_id`,`group_id`),
  KEY `student_group_group_id_foreign` (`group_id`),
  CONSTRAINT `student_group_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`),
  CONSTRAINT `student_group_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_group`
--

LOCK TABLES `student_group` WRITE;
/*!40000 ALTER TABLE `student_group` DISABLE KEYS */;
INSERT INTO `student_group` VALUES (1,1,1,'2024-09-01','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,2,2,'2024-09-02','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,1,'2024-09-01','2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,1,3,NULL,NULL,NULL),(5,2,3,NULL,NULL,NULL),(6,3,3,NULL,NULL,NULL),(7,1,4,NULL,NULL,NULL),(8,3,4,NULL,NULL,NULL),(9,3,7,NULL,NULL,NULL),(10,1,7,NULL,NULL,NULL),(11,2,7,NULL,NULL,NULL),(12,3,8,NULL,NULL,NULL),(13,1,8,NULL,NULL,NULL),(14,2,8,NULL,NULL,NULL),(15,3,9,NULL,NULL,NULL),(16,1,9,NULL,NULL,NULL),(17,2,9,NULL,NULL,NULL),(18,3,10,NULL,NULL,NULL),(19,1,10,NULL,NULL,NULL),(20,2,10,NULL,NULL,NULL),(21,3,11,NULL,NULL,NULL),(22,1,11,NULL,NULL,NULL),(23,2,11,NULL,NULL,NULL),(24,3,12,NULL,NULL,NULL),(25,1,12,NULL,NULL,NULL),(26,2,12,NULL,NULL,NULL),(27,1,13,NULL,NULL,NULL),(28,2,13,NULL,NULL,NULL),(29,3,13,NULL,NULL,NULL),(30,2,14,NULL,NULL,NULL),(31,3,14,NULL,NULL,NULL);
/*!40000 ALTER TABLE `student_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `student_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `student_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `students_user_id_unique` (`user_id`),
  CONSTRAINT `students_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,4,'Michael Brown','2024-01-15','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,5,'Sarah Wilson','2024-01-20','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,8,'Jangnne Smith',NULL,'2025-10-13 15:55:46','2025-10-13 15:55:46');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcourses`
--

DROP TABLE IF EXISTS `subcourses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcourses` (
  `subcourse_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `subcourse_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subcourse_number` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duration_hours` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`subcourse_id`),
  UNIQUE KEY `subcourses_course_id_subcourse_number_unique` (`course_id`,`subcourse_number`),
  CONSTRAINT `subcourses_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcourses`
--

LOCK TABLES `subcourses` WRITE;
/*!40000 ALTER TABLE `subcourses` DISABLE KEYS */;
INSERT INTO `subcourses` VALUES (1,1,'Calculus I',1,'Introduction to differential and integral calculus',45,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,1,'Linear Algebra',2,'Matrix theory and linear transformations',40,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,2,'Classical Mechanics',1,'Newtonian mechanics and motion',50,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(4,2,'Thermodynamics',2,'Heat, energy, and thermodynamic systems',45,'2025-10-12 23:19:20','2025-10-12 23:19:20'),(5,5,'efe',1,'efe',1,NULL,NULL);
/*!40000 ALTER TABLE `subcourses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_adjustments`
--

DROP TABLE IF EXISTS `teacher_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('bonus','deduction') COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_date` date NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_adjustments`
--

LOCK TABLES `teacher_adjustments` WRITE;
/*!40000 ALTER TABLE `teacher_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_payments`
--

DROP TABLE IF EXISTS `teacher_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `salary_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `teacher_payments_teacher_id_foreign` (`teacher_id`),
  KEY `teacher_payments_salary_id_foreign` (`salary_id`),
  KEY `teacher_payments_confirmed_by_foreign` (`confirmed_by`),
  CONSTRAINT `teacher_payments_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teacher_payments_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`salary_id`),
  CONSTRAINT `teacher_payments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_payments`
--

LOCK TABLES `teacher_payments` WRITE;
/*!40000 ALTER TABLE `teacher_payments` DISABLE KEYS */;
INSERT INTO `teacher_payments` VALUES (1,4,4,-720.00,'cash','2025-10-14 15:13:32',1,NULL,NULL,0,NULL,NULL),(2,4,5,0.00,'cash','2025-10-14 15:23:14',1,NULL,NULL,0,NULL,NULL),(3,4,6,0.00,'cash','2025-10-14 15:34:54',1,NULL,NULL,0,NULL,NULL),(4,2,3,480.00,'vodafone_cash','2025-10-14 15:39:37',1,NULL,NULL,0,NULL,NULL),(5,2,2,1200.00,'vodafone_cash','2025-10-14 15:45:38',1,NULL,NULL,0,NULL,NULL),(6,1,7,975.00,'bank_transfer','2025-10-14 15:54:12',1,NULL,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `teacher_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `teacher_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `teacher_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hire_date` date DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `salary_percentage` decimal(5,2) NOT NULL DEFAULT '80.00',
  `base_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bank_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','vodafone_cash') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `teachers_user_id_unique` (`user_id`),
  KEY `teachers_department_id_foreign` (`department_id`),
  CONSTRAINT `teachers_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`),
  CONSTRAINT `teachers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,2,'Professor Smith','2015-09-01',1,80.00,5000.00,'ACC123456789','bank_transfer','2025-10-12 23:19:20','2025-10-12 23:19:20'),(2,3,'Dr. Jones','2018-03-15',2,75.00,5500.00,'ACC987654321','vodafone_cash','2025-10-12 23:19:20','2025-10-12 23:19:20'),(3,7,'John Donnge',NULL,NULL,80.00,0.00,NULL,'cash','2025-10-13 15:55:46','2025-10-13 15:55:46'),(4,9,'teacher test','2025-10-14',2,80.00,0.00,NULL,'cash','2025-10-14 15:11:20','2025-10-14 15:11:20');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` bigint unsigned NOT NULL,
  `admin_type_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `welcome_sent` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_admin_type_id_foreign` (`admin_type_id`),
  CONSTRAINT `users_admin_type_id_foreign` FOREIGN KEY (`admin_type_id`) REFERENCES `admin_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`idroles`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'omar fathy','123456@gmail.com','$2a$12$cfXlxL5fbVEsKtcZAg8bX.KDPdPHzKWkyPJw9OpO4nNL52x..T3lC',1,1,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6ImtseDE3TTh6eUIycHJMcVg3T2JmU2c9PSIsInZhbHVlIjoiQVExQm1vQ1d4bDJCbTh1ZytMSjFQR1pvSnhDMGs0M1BwTGFORDlacEc0eGtjRUdjNnRabXg1bEFzSkZGVGZ5cmd6MEJqOEhvdjJiMDd5ZDgyMkc5ZlE9PSIsIm1hYyI6ImYwYWFhM2RjYzU3MmYzZTg3ZjdjNGNiMGM2MmNhN2FiOTAzOGI5MzE0OTBiZDZiMTk3OGYwNDI1MjdjZTM2MzEiLCJ0YWciOiIifQ=='),(2,'prof_smith','teacher1@gmail.com','$2y$12$z14g9WYzBFb8FokTvTqPvuWrL/nmETyMBhYebrmcCfdVjhJacUpCm',2,NULL,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6InU0RVBRZzlpQVNxdkVjTDN1Ui9GNGc9PSIsInZhbHVlIjoiZmEzR1JoeXo0WkFRc2tMbkpBblZvTDlQb3hNdzVkZE1HVis5dlN5V3JybDFUczVaaVYxWVZrYTBVSHBrRlExdjZTcWhsM0xTekVRQ3RNdFJOMHFLRUE9PSIsIm1hYyI6IjEwNjBhNzZhMzMwY2Q0N2YzNDUyZDQ3ZTEwMmZjYWZlNmMwOGMxODE4YmEyYTk0NDMwOTdlOWE0NGU2ZTA4OGEiLCJ0YWciOiIifQ=='),(3,'dr_jones','teacher2@gmail.com','$2y$12$pp3HTkD0yQlMXR9SOFn.f.K54hoGNxmuzMvdWOl8TJTvQwr4ON4Ku',2,NULL,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6ImxITExVTnMwaFNYUDgvRVBCZEZZUHc9PSIsInZhbHVlIjoiNVNIeitsOGRnZVM4MkdOVkxTQkkrSUpqd3lvaFNla1A0YUg2ZVBMVHJsRWx6MlVLT0Q3S2Q4czZaemdseWlqTE84T0ZuYytMRWs0ODRHY1NhdUk5cFE9PSIsIm1hYyI6IjcxZmQ4NjZjNmEzYmJjYzk3N2Q5OTcxMWFiNTk3Njk4NzZjZTg1NTExZjMwNDkxNGExNWRkYmJiMTdhMjhhMTUiLCJ0YWciOiIifQ=='),(4,'student_mike','student1@gmail.com','$2y$12$sEP7N0n3m188VMSrmyNvouph1zWKEpCuE6fLMthuF2Q0dkzmd00Z.',3,NULL,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6InozMzd1cWxvNkpXdTZwRnBaR0pUUmc9PSIsInZhbHVlIjoiNzl4c0Y0emZpdVdDd2FZY2VhS0hsbCtDZDJtRnQ5VFlmNlk3Z0FGZHpxKzRiN2dZdkRGZTVnOUVxRWVRVnVMVXZ6dndjdlllQUVHL1pEWnJUVmllMXc9PSIsIm1hYyI6Ijc2ZWMxZTNkNGEzM2M0YjNjNTU0NTYxN2FjMzVhMDkyM2E2MTk2ZjM3ZjE5YWU1ZTE1MDdmNzI1Yjk5ZjlkNGIiLCJ0YWciOiIifQ=='),(5,'student_sara','student2@gmail.com','$2y$12$.4kpHZMmwjCz3wjoDjysMerzOPYOeS.AB3LCTbj5Pd3vKREffd796',3,NULL,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6ImlGdk5rbUJNa3JrWUlxc1RCd3NIR0E9PSIsInZhbHVlIjoiSHpWTzV3eHd1NEhFQnlHRjU0R0szczdGbnlLMEVPa1V1alNOMFlkYThIRXR2cThYNHVUbURMOUVmWTU3ODREdkFIeDJuY0Q3empzRmhJeEtMUUhFOUE9PSIsIm1hYyI6IjdjZTMwZmNmNjkyNmE4NTgwNGQ4ZGEyYjhmMDQwNjg3ZjFkMTg3NGI0ZWYzNGM4ODhhNDNmNzcxNmRkOGE0NGIiLCJ0YWciOiIifQ=='),(6,'finance_leo','admin2@gmail.com','$2y$12$bVME5vAIaodG.nfcawZy5.BOyxuXnug13kZAcT28AGzkLZ2iN0ztG',1,2,1,0,'2025-10-12 23:19:20','2025-10-12 23:19:20','eyJpdiI6InJjclk0ZDIrWGFnRTFBeFBjOHgwcHc9PSIsInZhbHVlIjoibWlxcGFTSGptNVFHUTZhMmRvNG9XaTRxNlI2WmVFLzRNeHdRcDJZOU1INE05RGtBUjlqc2JoVkk2bXRaZWFlRExTNitJaWd4T2ZmdWZFSXBBdExiaUE9PSIsIm1hYyI6ImVhYTQ0NjYxNGFjZDc1MmMxMDQyMDA0ZTRkMDRkMzQwMjAzYjkxZDIxNDgwNmI4YjA0MjViMDUwZGY5ZTQ1MDMiLCJ0YWciOiIifQ=='),(7,'john','ngn@example.com','$2y$12$F4hGQC2wzsTMwxyq/061CO86nclarPXutgbznYPQssYXgOjfyk5Ny',2,NULL,1,0,NULL,NULL,'eyJpdiI6IitEWTd5LzlSNHJVQ2lEZXpEZWYrYmc9PSIsInZhbHVlIjoiWmNJSGRoQ1pKWDdiK1dmWnBTMUZrN0RtQTFIQTF5ZmpFUlFJRnZYZnFIcVJkaWg3ZEQzUStUQm41Q1hWZWpDdW1TbmdRRnh4RE9IQytTLzQrb1F1MkE9PSIsIm1hYyI6Ijc1NmQ1M2EzODY0NGNjNjUyYTM0YmVmOGY4ZDRkNDY5ZjYzOTZiNWQ5MjZkMjcxOTUxYjc5YmI2NTY5OWUxZmUiLCJ0YWciOiIifQ=='),(8,'jane_','janggnnne@example.com','$2y$12$U0nvyd4NofVwGxAmmfusY.XzOvYhlHvBfKm2Gyk13k2npH4dKaXsW',3,NULL,1,0,NULL,NULL,'eyJpdiI6ImVNd3hrVTZnN0hMY2IwWVgwb21xTHc9PSIsInZhbHVlIjoiMXFKQm9LcGVHUlk5cXFWL1g1QzJ4c1d1czVuL1dJTThoWWdxN1RoQVVwcjk3ZG04NWwrVVBuTVhwUkpvbGdkUjJJY1hlTi9Qd09tZ3N3Q0ZiUnlmcFE9PSIsIm1hYyI6IjY1NDUzNGUzMWEwYTY1M2RkZTkzODUzNjFkMmI5OTM5MDZhYzA4YWQ5MTIwZWVjYjIxMWVlNTZmZTY0ODU1YjgiLCJ0YWciOiIifQ=='),(9,'teacher test','teachertest@gmail.com','$2y$12$EU5Q..Vd9rGEcB7ao7XlBOPSKJmfPDLAqPungPcMlWnkg0MerYx0a',2,NULL,1,0,NULL,NULL,'eyJpdiI6IkVGZDdWeWo0Sno1SWhlc1NqU2RBbGc9PSIsInZhbHVlIjoidldJZEJ6Vzd0NWVsYUtvRU1VNTJRU2FvL1QvRTdOWE9hcWVIYkoyWndtTW9uSGlOMWdyeDhaQnYvTXVvUjQvQlNOL1RrVUs2b3JnWVZEbU52NjdUU1E9PSIsIm1hYyI6IjJiZTI0N2Y1NjE0M2ZkMmM0NmE5NzA5OTM3MDJkMmI4ZTQxZjY0YTc3NmQwYzk3MWYwNjQ0M2Y5YzNlNzhlNTAiLCJ0YWciOiIifQ=='),(10,'acc','acc@example.com','$2y$12$m5cdz9/Y/lf4ZFCU6qN29uutlqWF3g3zxOxPgsQ/uRixSD/45Vbfu',1,2,1,0,NULL,NULL,NULL),(11,'accountant','dvvdv@bghj.bb','$2y$12$huHUkhemZSfZC2PYHE//ku9LtrI33/9oShi3QpvK9Q5KQJJ64w6Fq',1,2,1,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-17  1:49:08
