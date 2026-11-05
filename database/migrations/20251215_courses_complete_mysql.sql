-- ============================================================================
-- GINTO Courses System Migration
-- ============================================================================
-- Generated: 2025-12-15 20:29:02
-- 
-- This migration creates the complete courses system including:
-- - subscription_plans: Free, Go, Plus, Pro subscription tiers
-- - course_categories: Fundamentals, AI, Development, Marketing
-- - courses: 4 courses (Touch Typing, Intro to AI, Web Development, AI Marketing)  
-- - lessons: 57 lessons with HTML content for free previews
-- - course_enrollments: User course progress tracking
-- - lesson_progress: Individual lesson completion tracking
-- - course_reviews: User reviews and ratings
-- - user_subscriptions: Active user subscriptions
-- - subscription_payments: Payment history
-- - achievements: Gamification achievements (6 total)
-- - user_achievements: User earned achievements
-- - study_streaks: User study streak tracking
--
-- Usage: Run this migration during install or to reset courses data
-- ============================================================================

-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: ginto
-- ------------------------------------------------------
-- Server version	8.0.44-0ubuntu0.24.04.1

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
-- Table structure for table `subscription_plans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `description` text COLLATE utf8mb4_unicode_ci,
  `features` json DEFAULT NULL,
  `max_lessons_per_course` int DEFAULT NULL,
  `max_courses` int DEFAULT NULL,
  `has_ai_tutor` tinyint(1) DEFAULT '0',
  `has_certificates` tinyint(1) DEFAULT '0',
  `has_priority_support` tinyint(1) DEFAULT '0',
  `badge_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'gray',
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `course_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'indigo',
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category_id` int unsigned DEFAULT NULL,
  `thumbnail_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preview_video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') COLLATE utf8mb4_unicode_ci DEFAULT 'beginner',
  `estimated_hours` decimal(5,1) DEFAULT NULL,
  `total_lessons` int DEFAULT '0',
  `min_plan_required` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'free',
  `is_featured` tinyint(1) DEFAULT '0',
  `is_published` tinyint(1) DEFAULT '1',
  `instructor_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_bio` text COLLATE utf8mb4_unicode_ci,
  `instructor_avatar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `learning_outcomes` json DEFAULT NULL,
  `prerequisites` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lessons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int unsigned NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `content_type` enum('text','video','interactive','quiz','embed') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `content_html` longtext COLLATE utf8mb4_unicode_ci,
  `content_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `embed_file` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lesson_order` int NOT NULL,
  `duration_minutes` int DEFAULT '10',
  `is_free_preview` tinyint(1) DEFAULT '0',
  `is_published` tinyint(1) DEFAULT '1',
  `resources` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_lesson` (`course_id`,`slug`),
  KEY `idx_course_order` (`course_id`,`lesson_order`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_enrollments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `course_enrollments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `enrolled_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `progress_percent` decimal(5,2) DEFAULT '0.00',
  `last_lesson_id` int unsigned DEFAULT NULL,
  `last_accessed_at` datetime DEFAULT NULL,
  `is_bookmarked` tinyint(1) DEFAULT '0',
  `certificate_issued` tinyint(1) DEFAULT '0',
  `certificate_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`user_id`,`course_id`),
  KEY `course_id` (`course_id`),
  KEY `last_lesson_id` (`last_lesson_id`),
  KEY `idx_user_progress` (`user_id`,`progress_percent`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_3` FOREIGN KEY (`last_lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_progress`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `lesson_progress` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `lesson_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `status` enum('not_started','in_progress','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'not_started',
  `progress_percent` decimal(5,2) DEFAULT '0.00',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `time_spent_seconds` int DEFAULT '0',
  `quiz_score` decimal(5,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_progress` (`user_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `course_id` (`course_id`),
  KEY `idx_user_course` (`user_id`,`course_id`),
  CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_progress_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `course_reviews` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `rating` tinyint NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_text` text COLLATE utf8mb4_unicode_ci,
  `is_verified_purchase` tinyint(1) DEFAULT '0',
  `is_approved` tinyint(1) DEFAULT '1',
  `helpful_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_review` (`user_id`,`course_id`),
  KEY `idx_course_rating` (`course_id`,`rating`),
  CONSTRAINT `course_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_reviews_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_reviews_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_subscriptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `plan_id` int unsigned NOT NULL,
  `status` enum('active','cancelled','expired','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'PHP',
  `is_trial` tinyint(1) DEFAULT '0',
  `trial_ends_at` datetime DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `subscription_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `subscription_id` int unsigned DEFAULT NULL,
  `plan_id` int unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'PHP',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `achievements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `achievements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int DEFAULT '0',
  `criteria` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_achievements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `user_achievements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `achievement_id` int unsigned NOT NULL,
  `earned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_achievement` (`user_id`,`achievement_id`),
  KEY `achievement_id` (`achievement_id`),
  CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `study_streaks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `study_streaks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `current_streak` int DEFAULT '0',
  `longest_streak` int DEFAULT '0',
  `last_study_date` date DEFAULT NULL,
  `total_study_days` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `study_streaks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-15 20:27:55
-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: ginto
-- ------------------------------------------------------
-- Server version	8.0.44-0ubuntu0.24.04.1

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
-- Dumping data for table `subscription_plans`
--

LOCK TABLES `subscription_plans` WRITE;
/*!40000 ALTER TABLE `subscription_plans` DISABLE KEYS */;
INSERT IGNORE INTO `subscription_plans` (`id`, `name`, `display_name`, `price_monthly`, `price_currency`, `description`, `features`, `max_lessons_per_course`, `max_courses`, `has_ai_tutor`, `has_certificates`, `has_priority_support`, `badge_color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'free','Free',0.00,'PHP','See what learning can do','[\"Access to 3 free lessons per course\", \"Basic course previews\", \"Limited AI chat assistance\", \"Community forum access\"]',3,NULL,0,0,0,'gray',1,1,'2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `subscription_plans` (`id`, `name`, `display_name`, `price_monthly`, `price_currency`, `description`, `features`, `max_lessons_per_course`, `max_courses`, `has_ai_tutor`, `has_certificates`, `has_priority_support`, `badge_color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (2,'go','Go',300.00,'PHP','Learn faster with smarter tools','[\"Access to 10 lessons per course\", \"AI-powered study assistance\", \"Practice exercises\", \"Progress tracking\", \"Mobile access\", \"Download course materials\"]',10,NULL,1,0,0,'green',2,1,'2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `subscription_plans` (`id`, `name`, `display_name`, `price_monthly`, `price_currency`, `description`, `features`, `max_lessons_per_course`, `max_courses`, `has_ai_tutor`, `has_certificates`, `has_priority_support`, `badge_color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (3,'plus','Plus',1100.00,'PHP','Unlock the full learning experience','[\"Unlimited access to all lessons\", \"Advanced AI tutor\", \"Personalized learning paths\", \"Course certificates\", \"Priority support\", \"Live Q&A sessions\", \"Project-based learning\"]',NULL,NULL,1,1,1,'purple',3,1,'2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `subscription_plans` (`id`, `name`, `display_name`, `price_monthly`, `price_currency`, `description`, `features`, `max_lessons_per_course`, `max_courses`, `has_ai_tutor`, `has_certificates`, `has_priority_support`, `badge_color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (4,'pro','Pro',9990.00,'PHP','Maximize your learning potential','[\"Everything in Plus\", \"1-on-1 mentorship sessions\", \"Career guidance\", \"Job placement assistance\", \"Exclusive Pro community\", \"Early access to new courses\", \"Custom learning plans\", \"API access for integrations\"]',NULL,NULL,1,1,1,'indigo',4,1,'2025-12-15 05:46:17','2025-12-15 05:46:17');
/*!40000 ALTER TABLE `subscription_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `course_categories`
--

LOCK TABLES `course_categories` WRITE;
/*!40000 ALTER TABLE `course_categories` DISABLE KEYS */;
INSERT IGNORE INTO `course_categories` (`id`, `slug`, `name`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES (1,'fundamentals','Fundamentals','Core skills for digital literacy','fa-keyboard','blue',1,1,'2025-12-15 05:46:17');
INSERT IGNORE INTO `course_categories` (`id`, `slug`, `name`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES (2,'ai','AI & ML','Artificial Intelligence and Machine Learning','fa-brain','purple',2,1,'2025-12-15 05:46:17');
INSERT IGNORE INTO `course_categories` (`id`, `slug`, `name`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES (3,'development','Development','Web and software development','fa-code','green',3,1,'2025-12-15 05:46:17');
INSERT IGNORE INTO `course_categories` (`id`, `slug`, `name`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES (4,'marketing','AI Marketing','AI-powered digital marketing','fa-chart-line','orange',4,1,'2025-12-15 05:46:17');
/*!40000 ALTER TABLE `course_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT IGNORE INTO `courses` (`id`, `slug`, `title`, `subtitle`, `description`, `category_id`, `thumbnail_url`, `preview_video_url`, `difficulty_level`, `estimated_hours`, `total_lessons`, `min_plan_required`, `is_featured`, `is_published`, `instructor_name`, `instructor_bio`, `instructor_avatar`, `tags`, `learning_outcomes`, `prerequisites`, `created_at`, `updated_at`) VALUES (1,'touch-typing','Touch Typing Mastery','Build speed and accuracy with proper keyboard technique','Master the art of touch typing with our comprehensive course designed for programmers and professionals. Learn proper finger placement, build muscle memory, and increase your typing speed from beginner to professional level. This foundational skill will boost your productivity in every digital task.',1,NULL,NULL,'beginner',15.0,15,'free',1,1,'Ginto Academy',NULL,NULL,NULL,'[\"Type at 60+ WPM with 95% accuracy\", \"Use all 10 fingers correctly\", \"Type without looking at the keyboard\", \"Master special characters and numbers\", \"Develop proper ergonomic habits\"]','[\"Basic computer familiarity\", \"Access to a standard keyboard\"]','2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `courses` (`id`, `slug`, `title`, `subtitle`, `description`, `category_id`, `thumbnail_url`, `preview_video_url`, `difficulty_level`, `estimated_hours`, `total_lessons`, `min_plan_required`, `is_featured`, `is_published`, `instructor_name`, `instructor_bio`, `instructor_avatar`, `tags`, `learning_outcomes`, `prerequisites`, `created_at`, `updated_at`) VALUES (2,'intro-to-ai','Introduction to AI','Learn the fundamentals of artificial intelligence and machine learning','Discover the fascinating world of Artificial Intelligence. This course covers AI fundamentals, machine learning concepts, neural networks, and practical applications. Perfect for beginners who want to understand how AI works and its impact on various industries.',2,NULL,NULL,'beginner',20.0,12,'free',1,1,'Ginto Academy',NULL,NULL,NULL,'[\"Understand core AI and ML concepts\", \"Recognize different types of machine learning\", \"Explain how neural networks work\", \"Identify AI applications in real world\", \"Use AI tools effectively\", \"Understand ethical considerations in AI\"]','[\"Basic math understanding\", \"Curiosity about technology\"]','2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `courses` (`id`, `slug`, `title`, `subtitle`, `description`, `category_id`, `thumbnail_url`, `preview_video_url`, `difficulty_level`, `estimated_hours`, `total_lessons`, `min_plan_required`, `is_featured`, `is_published`, `instructor_name`, `instructor_bio`, `instructor_avatar`, `tags`, `learning_outcomes`, `prerequisites`, `created_at`, `updated_at`) VALUES (3,'web-development','Web Development Basics','Master HTML, CSS, and JavaScript to build modern web applications','Start your web development journey with this comprehensive course. Learn HTML for structure, CSS for styling, and JavaScript for interactivity. By the end, you will be able to build responsive, modern websites from scratch.',3,NULL,NULL,'beginner',40.0,20,'free',1,1,'Ginto Academy',NULL,NULL,NULL,'[\"Write semantic HTML5 markup\", \"Style pages with modern CSS3\", \"Create responsive layouts with Flexbox and Grid\", \"Add interactivity with JavaScript\", \"Understand DOM manipulation\", \"Build complete web pages from scratch\"]','[\"Basic computer skills\", \"Text editor familiarity\"]','2025-12-15 05:46:17','2025-12-15 05:46:17');
INSERT IGNORE INTO `courses` (`id`, `slug`, `title`, `subtitle`, `description`, `category_id`, `thumbnail_url`, `preview_video_url`, `difficulty_level`, `estimated_hours`, `total_lessons`, `min_plan_required`, `is_featured`, `is_published`, `instructor_name`, `instructor_bio`, `instructor_avatar`, `tags`, `learning_outcomes`, `prerequisites`, `created_at`, `updated_at`) VALUES (4,'ai-marketing','Agentic Digital Marketing','Leverage AI agents to automate campaigns, analyze data, and scale your marketing','Transform your marketing strategy with AI-powered automation. Learn to use AI agents for content creation, campaign optimization, audience targeting, and performance analysis. This course teaches you to work alongside AI to achieve marketing results at scale.',4,NULL,NULL,'intermediate',25.0,10,'go',0,1,'Ginto Academy',NULL,NULL,NULL,'[\"Set up AI agents for marketing tasks\", \"Automate content creation workflows\", \"Use AI for audience analysis\", \"Optimize campaigns with AI insights\", \"Scale marketing efforts efficiently\", \"Measure and improve AI-driven results\"]','[\"Basic marketing knowledge\", \"Familiarity with digital platforms\"]','2025-12-15 05:46:17','2025-12-15 05:46:17');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (1,1,'introduction','Introduction to Touch Typing','Learn what touch typing is and why it matters for your productivity.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Welcome to Touch Typing Mastery! 🎹</h2>\n    \n    <div class=\"bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/30 rounded-xl p-6 mb-6\">\n        <p class=\"text-lg mb-0\">Touch typing is the ability to type without looking at the keyboard. It\'s a fundamental skill that will <strong>boost your productivity</strong> in every digital task you do.</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Why Learn Touch Typing?</h3>\n    <ul class=\"space-y-2\">\n        <li>✅ <strong>Speed:</strong> Professional typists reach 60-100+ WPM</li>\n        <li>✅ <strong>Accuracy:</strong> Fewer errors mean less time correcting</li>\n        <li>✅ <strong>Focus:</strong> Keep your eyes on the screen, not the keyboard</li>\n        <li>✅ <strong>Health:</strong> Proper posture reduces strain and injury</li>\n    </ul>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">What You\'ll Learn</h3>\n    <div class=\"grid md:grid-cols-2 gap-4\">\n        <div class=\"bg-gray-100 dark:bg-gray-800 rounded-lg p-4\">\n            <h4 class=\"font-medium mb-2\">📍 Home Row Position</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">The foundation of touch typing - where your fingers rest</p>\n        </div>\n        <div class=\"bg-gray-100 dark:bg-gray-800 rounded-lg p-4\">\n            <h4 class=\"font-medium mb-2\">🖐️ Finger Assignments</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Which finger presses which key</p>\n        </div>\n        <div class=\"bg-gray-100 dark:bg-gray-800 rounded-lg p-4\">\n            <h4 class=\"font-medium mb-2\">⚡ Speed Building</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Progressive exercises to increase WPM</p>\n        </div>\n        <div class=\"bg-gray-100 dark:bg-gray-800 rounded-lg p-4\">\n            <h4 class=\"font-medium mb-2\">🎯 Accuracy Training</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Techniques for error-free typing</p>\n        </div>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-indigo-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🚀 Ready to Practice?</h4>\n        <p class=\"mb-4\">Try our interactive typing trainer right now!</p>\n        <a href=\"/typing.html\" target=\"_blank\" class=\"inline-block bg-white text-indigo-600 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition-colors\">\n            Launch Typing Trainer →\n        </a>\n    </div>\n</div>\n',NULL,NULL,1,10,1,1,NULL,'2025-12-15 05:59:52','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (2,1,'home-row-basics','The Home Row Position','Master the foundation: ASDF JKL; - where your fingers rest.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">The Home Row Position 🏠</h2>\n    \n    <p class=\"text-lg\">The <strong>home row</strong> is the foundation of touch typing. This is where your fingers rest when you\'re not actively typing.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Finding the Home Row</h3>\n    <p>Look at your keyboard and find these keys in the middle row:</p>\n    \n    <div class=\"my-6 p-6 bg-gray-900 text-white rounded-xl font-mono text-center\">\n        <div class=\"text-2xl tracking-widest\">\n            <span class=\"text-red-400\">A</span>\n            <span class=\"text-orange-400\">S</span>\n            <span class=\"text-yellow-400\">D</span>\n            <span class=\"text-green-400 border-b-2 border-green-400\">F</span>\n            <span class=\"mx-4 text-gray-500\">|</span>\n            <span class=\"text-green-400 border-b-2 border-green-400\">J</span>\n            <span class=\"text-yellow-400\">K</span>\n            <span class=\"text-orange-400\">L</span>\n            <span class=\"text-red-400\">;</span>\n        </div>\n        <p class=\"text-sm mt-3 text-gray-400\">Notice the bumps on F and J - these help you find home row without looking!</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Finger Placement</h3>\n    <div class=\"grid md:grid-cols-2 gap-6\">\n        <div>\n            <h4 class=\"font-medium mb-2 text-blue-600 dark:text-blue-400\">Left Hand</h4>\n            <ul class=\"space-y-1 text-sm\">\n                <li>🖐️ Pinky → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">A</kbd></li>\n                <li>🖐️ Ring → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">S</kbd></li>\n                <li>🖐️ Middle → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">D</kbd></li>\n                <li>🖐️ Index → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">F</kbd></li>\n            </ul>\n        </div>\n        <div>\n            <h4 class=\"font-medium mb-2 text-purple-600 dark:text-purple-400\">Right Hand</h4>\n            <ul class=\"space-y-1 text-sm\">\n                <li>🖐️ Index → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">J</kbd></li>\n                <li>🖐️ Middle → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">K</kbd></li>\n                <li>🖐️ Ring → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">L</kbd></li>\n                <li>🖐️ Pinky → <kbd class=\"px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded\">;</kbd></li>\n            </ul>\n        </div>\n    </div>\n    \n    <div class=\"mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg\">\n        <p class=\"text-sm mb-0\">💡 <strong>Pro Tip:</strong> Both thumbs rest on the spacebar. Always return to home row after pressing any key!</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-8 mb-3\">Practice Exercise</h3>\n    <p>Type this sequence slowly, focusing on correct finger placement:</p>\n    <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg font-mono text-lg\">\n        asdf jkl; asdf jkl; fjfj dkdk slsl a;a;\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🎯 Ready to Practice?</h4>\n        <p class=\"mb-4\">Use our interactive trainer to master the home row!</p>\n        <a href=\"/typing.html\" target=\"_blank\" class=\"inline-block bg-white text-green-600 font-semibold px-6 py-3 rounded-lg hover:bg-green-50 transition-colors\">\n            Start Practicing →\n        </a>\n    </div>\n</div>\n',NULL,NULL,2,15,1,1,NULL,'2025-12-15 05:59:52','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (3,1,'first-practice','Your First Typing Practice','Start practicing with the interactive typing trainer.','embed','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Your First Typing Practice ⌨️</h2>\n    \n    <p class=\"text-lg\">Now that you know the home row, let\'s put it into practice! This lesson includes exercises to build muscle memory.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Before You Start</h3>\n    <div class=\"grid md:grid-cols-3 gap-4 my-6\">\n        <div class=\"text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg\">\n            <div class=\"text-3xl mb-2\">🪑</div>\n            <p class=\"text-sm font-medium\">Sit up straight</p>\n        </div>\n        <div class=\"text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg\">\n            <div class=\"text-3xl mb-2\">💻</div>\n            <p class=\"text-sm font-medium\">Screen at eye level</p>\n        </div>\n        <div class=\"text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg\">\n            <div class=\"text-3xl mb-2\">🖐️</div>\n            <p class=\"text-sm font-medium\">Wrists slightly elevated</p>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Exercise 1: Home Row Only</h3>\n    <p>Type each line 3 times, focusing on accuracy over speed:</p>\n    <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg font-mono space-y-2\">\n        <p>asdf jkl; asdf jkl;</p>\n        <p>fjdk slal fjdk slal</p>\n        <p>add sad dad lad fad</p>\n        <p>ask all fall flask</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Exercise 2: Simple Words</h3>\n    <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg font-mono space-y-2\">\n        <p>a sad lad; a glad dad</p>\n        <p>all fall; ask flask</p>\n        <p>salad; add salt</p>\n    </div>\n    \n    <div class=\"mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg\">\n        <h4 class=\"font-medium mb-2\">📊 Track Your Progress</h4>\n        <p class=\"text-sm mb-0\">Aim for 100% accuracy first. Speed will come naturally with practice. Most beginners start at 10-20 WPM and can reach 40+ WPM within a few weeks of daily practice.</p>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🚀 Interactive Practice</h4>\n        <p class=\"mb-4\">Our typing trainer tracks your speed and accuracy in real-time!</p>\n        <a href=\"/typing.html\" target=\"_blank\" class=\"inline-block bg-white text-indigo-600 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition-colors\">\n            Launch Typing Trainer →\n        </a>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gray-100 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600\">\n        <h4 class=\"font-bold text-lg mb-2\">🔓 Want More?</h4>\n        <p class=\"text-gray-600 dark:text-gray-400 mb-4\">Upgrade to access 12 more lessons including:</p>\n        <ul class=\"text-sm space-y-1 text-gray-600 dark:text-gray-400 mb-4\">\n            <li>• Upper row mastery (QWERTY)</li>\n            <li>• Number row techniques</li>\n            <li>• Special characters & symbols</li>\n            <li>• Speed building drills</li>\n            <li>• Real-world typing exercises</li>\n        </ul>\n        <a href=\"/courses/pricing\" class=\"inline-block bg-indigo-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors\">\n            View Plans →\n        </a>\n    </div>\n</div>\n',NULL,'typing.html',3,20,1,1,NULL,'2025-12-15 05:59:52','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (4,1,'top-row','Mastering the Top Row','Learn QWERTY and the numbers row with proper technique.','text',NULL,NULL,NULL,4,15,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (5,1,'bottom-row','The Bottom Row Keys','Master ZXCV and build complete keyboard coverage.','text',NULL,NULL,NULL,5,15,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (6,1,'capital-letters','Shift Keys and Capitals','Learn proper pinky technique for shift keys.','text',NULL,NULL,NULL,6,12,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (7,1,'common-words','Common Words Practice','Build speed with the 100 most common English words.','embed',NULL,NULL,'typing.html',7,20,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (8,1,'punctuation','Punctuation and Symbols','Master periods, commas, and common punctuation.','text',NULL,NULL,NULL,8,15,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (9,1,'numbers-row','Number Row Mastery','Type numbers without looking at the keyboard.','text',NULL,NULL,NULL,9,15,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (10,1,'speed-building','Speed Building Techniques','Strategies to increase your WPM safely.','text',NULL,NULL,NULL,10,12,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (11,1,'special-characters','Special Characters for Programmers','Master brackets, braces, and coding symbols.','text',NULL,NULL,NULL,11,15,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (12,1,'accuracy-focus','Accuracy Over Speed','Why accuracy matters more and how to improve it.','text',NULL,NULL,NULL,12,10,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (13,1,'advanced-practice','Advanced Typing Drills','Challenge yourself with complex text patterns.','embed',NULL,NULL,'typing.html',13,25,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (14,1,'ergonomics','Ergonomics and Healthy Habits','Prevent RSI and maintain typing health.','text',NULL,NULL,NULL,14,10,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (15,1,'certification-test','Typing Certification Test','Test your skills and earn your certificate.','embed',NULL,NULL,'typing.html',15,30,0,1,NULL,'2025-12-15 05:59:52','2025-12-15 05:59:52');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (16,2,'what-is-ai','What is Artificial Intelligence?','Understand the definition and scope of AI.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">What is Artificial Intelligence? 🤖</h2>\n    \n    <div class=\"bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/30 rounded-xl p-6 mb-6\">\n        <p class=\"text-lg mb-0\"><strong>Artificial Intelligence (AI)</strong> is the simulation of human intelligence by machines. It enables computers to learn, reason, and make decisions.</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">AI is Everywhere</h3>\n    <p>You interact with AI daily, often without realizing it:</p>\n    \n    <div class=\"grid md:grid-cols-2 gap-4 my-6\">\n        <div class=\"flex items-start gap-3 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <span class=\"text-2xl\">📱</span>\n            <div>\n                <h4 class=\"font-medium\">Voice Assistants</h4>\n                <p class=\"text-sm text-gray-600 dark:text-gray-400\">Siri, Alexa, Google Assistant</p>\n            </div>\n        </div>\n        <div class=\"flex items-start gap-3 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <span class=\"text-2xl\">🎬</span>\n            <div>\n                <h4 class=\"font-medium\">Recommendations</h4>\n                <p class=\"text-sm text-gray-600 dark:text-gray-400\">Netflix, Spotify, YouTube</p>\n            </div>\n        </div>\n        <div class=\"flex items-start gap-3 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <span class=\"text-2xl\">📧</span>\n            <div>\n                <h4 class=\"font-medium\">Spam Filters</h4>\n                <p class=\"text-sm text-gray-600 dark:text-gray-400\">Gmail, Outlook</p>\n            </div>\n        </div>\n        <div class=\"flex items-start gap-3 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <span class=\"text-2xl\">🚗</span>\n            <div>\n                <h4 class=\"font-medium\">Navigation</h4>\n                <p class=\"text-sm text-gray-600 dark:text-gray-400\">Google Maps, Waze</p>\n            </div>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Types of AI</h3>\n    <div class=\"space-y-4\">\n        <div class=\"p-4 border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20\">\n            <h4 class=\"font-medium\">Narrow AI (Weak AI)</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400 mb-0\">Designed for specific tasks. This is what we have today - ChatGPT, image recognition, game-playing AI.</p>\n        </div>\n        <div class=\"p-4 border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20\">\n            <h4 class=\"font-medium\">General AI (Strong AI)</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400 mb-0\">Human-level intelligence across all domains. This doesn\'t exist yet but is actively researched.</p>\n        </div>\n        <div class=\"p-4 border-l-4 border-pink-500 bg-pink-50 dark:bg-pink-900/20\">\n            <h4 class=\"font-medium\">Super AI</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400 mb-0\">Surpasses human intelligence. Currently theoretical.</p>\n        </div>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">💬 Try AI Right Now!</h4>\n        <p class=\"mb-4\">Chat with our AI assistant to experience AI firsthand.</p>\n        <a href=\"/chat\" class=\"inline-block bg-white text-blue-600 font-semibold px-6 py-3 rounded-lg hover:bg-blue-50 transition-colors\">\n            Open AI Chat →\n        </a>\n    </div>\n</div>\n',NULL,NULL,1,15,1,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (17,2,'ai-history','Brief History of AI','From Turing to modern deep learning.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Brief History of AI 📜</h2>\n    \n    <p class=\"text-lg\">AI isn\'t new—the dream of intelligent machines dates back decades. Let\'s explore the key milestones.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Timeline of AI</h3>\n    \n    <div class=\"relative border-l-2 border-indigo-500 pl-6 space-y-8 my-6\">\n        <div class=\"relative\">\n            <div class=\"absolute -left-8 w-4 h-4 bg-indigo-500 rounded-full\"></div>\n            <div class=\"font-bold text-indigo-600 dark:text-indigo-400\">1950</div>\n            <h4 class=\"font-medium\">The Turing Test</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Alan Turing proposes a test for machine intelligence: Can a machine fool a human into thinking it\'s human?</p>\n        </div>\n        <div class=\"relative\">\n            <div class=\"absolute -left-8 w-4 h-4 bg-indigo-500 rounded-full\"></div>\n            <div class=\"font-bold text-indigo-600 dark:text-indigo-400\">1956</div>\n            <h4 class=\"font-medium\">AI is Born</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">The term \"Artificial Intelligence\" is coined at the Dartmouth Conference.</p>\n        </div>\n        <div class=\"relative\">\n            <div class=\"absolute -left-8 w-4 h-4 bg-indigo-500 rounded-full\"></div>\n            <div class=\"font-bold text-indigo-600 dark:text-indigo-400\">1997</div>\n            <h4 class=\"font-medium\">Deep Blue Beats Kasparov</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">IBM\'s chess computer defeats world champion Garry Kasparov.</p>\n        </div>\n        <div class=\"relative\">\n            <div class=\"absolute -left-8 w-4 h-4 bg-indigo-500 rounded-full\"></div>\n            <div class=\"font-bold text-indigo-600 dark:text-indigo-400\">2012</div>\n            <h4 class=\"font-medium\">Deep Learning Revolution</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Neural networks achieve breakthrough performance in image recognition.</p>\n        </div>\n        <div class=\"relative\">\n            <div class=\"absolute -left-8 w-4 h-4 bg-green-500 rounded-full\"></div>\n            <div class=\"font-bold text-green-600 dark:text-green-400\">2022-Now</div>\n            <h4 class=\"font-medium\">ChatGPT Era</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Large Language Models bring AI to the mainstream. We are here!</p>\n        </div>\n    </div>\n    \n    <div class=\"p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg\">\n        <p class=\"text-sm mb-0\">💡 <strong>Fun Fact:</strong> The term \"AI Winter\" refers to periods when AI research funding dried up due to unmet expectations. We\'re currently in an \"AI Summer\" of unprecedented progress!</p>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🧠 Experience Modern AI</h4>\n        <p class=\"mb-4\">See how far we\'ve come—chat with our AI assistant!</p>\n        <a href=\"/chat\" class=\"inline-block bg-white text-purple-600 font-semibold px-6 py-3 rounded-lg hover:bg-purple-50 transition-colors\">\n            Try AI Chat →\n        </a>\n    </div>\n</div>\n',NULL,NULL,2,12,1,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (18,2,'ai-in-daily-life','AI in Your Daily Life','Discover AI applications you use every day.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">AI in Your Daily Life 🌍</h2>\n    \n    <p class=\"text-lg\">AI is more integrated into your daily routine than you might think. Let\'s explore how AI touches almost everything you do.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Morning Routine</h3>\n    <div class=\"space-y-3\">\n        <div class=\"flex items-center gap-3 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg\">\n            <span class=\"text-xl\">⏰</span>\n            <span><strong>Smart Alarm:</strong> Learns your sleep patterns to wake you at optimal times</span>\n        </div>\n        <div class=\"flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg\">\n            <span class=\"text-xl\">📰</span>\n            <span><strong>News Feed:</strong> AI curates stories based on your interests</span>\n        </div>\n        <div class=\"flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg\">\n            <span class=\"text-xl\">🗺️</span>\n            <span><strong>Commute:</strong> Maps predict traffic and suggest fastest routes</span>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">At Work</h3>\n    <div class=\"grid md:grid-cols-2 gap-4\">\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium mb-2\">📧 Email</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Smart compose, spam filtering, priority inbox</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium mb-2\">📝 Writing</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Grammar checking, text suggestions, translation</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium mb-2\">🔍 Search</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Google understands intent, not just keywords</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium mb-2\">📅 Calendar</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Smart scheduling, meeting suggestions</p>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Entertainment</h3>\n    <p>Every streaming service uses AI to recommend content:</p>\n    <ul class=\"space-y-2\">\n        <li>🎬 <strong>Netflix:</strong> 80% of watched content comes from recommendations</li>\n        <li>🎵 <strong>Spotify:</strong> Discover Weekly analyzes billions of data points</li>\n        <li>📺 <strong>YouTube:</strong> AI keeps you watching with personalized suggestions</li>\n        <li>🎮 <strong>Gaming:</strong> NPCs use AI for realistic behavior</li>\n    </ul>\n    \n    <div class=\"mt-8 p-6 bg-gray-100 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600\">\n        <h4 class=\"font-bold text-lg mb-2\">🔓 Continue Learning</h4>\n        <p class=\"text-gray-600 dark:text-gray-400 mb-4\">Upgrade to explore 9 more lessons including:</p>\n        <ul class=\"text-sm space-y-1 text-gray-600 dark:text-gray-400 mb-4\">\n            <li>• Machine Learning fundamentals</li>\n            <li>• Neural Networks explained</li>\n            <li>• Natural Language Processing</li>\n            <li>• Computer Vision basics</li>\n            <li>• Building your first AI project</li>\n        </ul>\n        <a href=\"/courses/pricing\" class=\"inline-block bg-purple-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors\">\n            Upgrade Now →\n        </a>\n    </div>\n</div>\n',NULL,NULL,3,10,1,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (19,2,'machine-learning-basics','Introduction to Machine Learning','What is ML and how does it differ from traditional programming?','text',NULL,NULL,NULL,4,20,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (20,2,'supervised-learning','Supervised Learning Explained','Learn about labeled data and prediction.','text',NULL,NULL,NULL,5,18,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (21,2,'unsupervised-learning','Unsupervised Learning','Clustering and pattern discovery.','text',NULL,NULL,NULL,6,15,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (22,2,'neural-networks-intro','Introduction to Neural Networks','How artificial neurons work together.','text',NULL,NULL,NULL,7,20,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (23,2,'deep-learning','Deep Learning Fundamentals','Understanding multi-layer networks.','text',NULL,NULL,NULL,8,18,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (24,2,'nlp-basics','Natural Language Processing','How AI understands human language.','text',NULL,NULL,NULL,9,15,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (25,2,'computer-vision','Computer Vision Basics','Teaching machines to see and interpret images.','text',NULL,NULL,NULL,10,15,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (26,2,'ai-ethics','AI Ethics and Responsibility','Understanding bias, fairness, and responsible AI.','text',NULL,NULL,NULL,11,20,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (27,2,'ai-future','The Future of AI','Emerging trends and career opportunities.','text',NULL,NULL,NULL,12,15,0,1,NULL,'2025-12-15 06:00:17','2025-12-15 06:00:17');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (28,3,'web-intro','Introduction to Web Development','Understanding how the web works.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Introduction to Web Development 🌐</h2>\n    \n    <div class=\"bg-gradient-to-r from-green-500/10 to-teal-500/10 border border-green-500/30 rounded-xl p-6 mb-6\">\n        <p class=\"text-lg mb-0\">Web development is the art of building websites and web applications. It\'s one of the most in-demand skills in tech!</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">The Three Pillars of Web Development</h3>\n    \n    <div class=\"grid md:grid-cols-3 gap-4 my-6\">\n        <div class=\"p-6 bg-orange-50 dark:bg-orange-900/20 rounded-xl text-center\">\n            <div class=\"text-4xl mb-3\">📄</div>\n            <h4 class=\"font-bold text-orange-600 dark:text-orange-400\">HTML</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Structure & Content</p>\n            <p class=\"text-xs mt-2\">The skeleton of every webpage</p>\n        </div>\n        <div class=\"p-6 bg-blue-50 dark:bg-blue-900/20 rounded-xl text-center\">\n            <div class=\"text-4xl mb-3\">🎨</div>\n            <h4 class=\"font-bold text-blue-600 dark:text-blue-400\">CSS</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Style & Design</p>\n            <p class=\"text-xs mt-2\">Makes things look beautiful</p>\n        </div>\n        <div class=\"p-6 bg-yellow-50 dark:bg-yellow-900/20 rounded-xl text-center\">\n            <div class=\"text-4xl mb-3\">⚡</div>\n            <h4 class=\"font-bold text-yellow-600 dark:text-yellow-400\">JavaScript</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Interactivity</p>\n            <p class=\"text-xs mt-2\">Brings pages to life</p>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Why Learn Web Development?</h3>\n    <ul class=\"space-y-2\">\n        <li>💰 <strong>High Demand:</strong> Web developers earn $70-150k+ annually</li>\n        <li>🏠 <strong>Remote Friendly:</strong> Work from anywhere in the world</li>\n        <li>🚀 <strong>Create Anything:</strong> Build your ideas into reality</li>\n        <li>📈 <strong>Always Growing:</strong> The web evolves, so do opportunities</li>\n    </ul>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">What You\'ll Build</h3>\n    <p>By the end of this course, you\'ll be able to create:</p>\n    <div class=\"grid grid-cols-2 md:grid-cols-4 gap-3\">\n        <div class=\"p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm\">Personal Portfolio</div>\n        <div class=\"p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm\">Landing Pages</div>\n        <div class=\"p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm\">Contact Forms</div>\n        <div class=\"p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm\">Interactive Apps</div>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-green-600 to-teal-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🛠️ Ready to Code?</h4>\n        <p class=\"mb-4\">Let\'s dive into HTML in the next lesson!</p>\n    </div>\n</div>\n',NULL,NULL,1,12,1,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (29,3,'html-basics','HTML Fundamentals','Structure your first web page with HTML.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">HTML Fundamentals 📝</h2>\n    \n    <p class=\"text-lg\">HTML (HyperText Markup Language) is the foundation of every webpage. Let\'s learn the basics!</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Your First HTML</h3>\n    <p>Every HTML document has this basic structure:</p>\n    \n    <div class=\"my-6 bg-gray-900 text-gray-100 rounded-xl overflow-hidden\">\n        <div class=\"bg-gray-800 px-4 py-2 text-sm text-gray-400\">index.html</div>\n        <pre class=\"p-4 overflow-x-auto text-sm\"><code>&lt;!DOCTYPE html&gt;\n&lt;html&gt;\n  &lt;head&gt;\n    &lt;title&gt;My First Page&lt;/title&gt;\n  &lt;/head&gt;\n  &lt;body&gt;\n    &lt;h1&gt;Hello, World!&lt;/h1&gt;\n    &lt;p&gt;This is my first webpage.&lt;/p&gt;\n  &lt;/body&gt;\n&lt;/html&gt;</code></pre>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Common HTML Tags</h3>\n    <div class=\"overflow-x-auto\">\n        <table class=\"w-full text-sm\">\n            <thead class=\"bg-gray-100 dark:bg-gray-800\">\n                <tr>\n                    <th class=\"p-3 text-left\">Tag</th>\n                    <th class=\"p-3 text-left\">Purpose</th>\n                    <th class=\"p-3 text-left\">Example</th>\n                </tr>\n            </thead>\n            <tbody class=\"divide-y divide-gray-200 dark:divide-gray-700\">\n                <tr><td class=\"p-3 font-mono\">&lt;h1&gt;-&lt;h6&gt;</td><td class=\"p-3\">Headings</td><td class=\"p-3 font-mono\">&lt;h1&gt;Title&lt;/h1&gt;</td></tr>\n                <tr><td class=\"p-3 font-mono\">&lt;p&gt;</td><td class=\"p-3\">Paragraph</td><td class=\"p-3 font-mono\">&lt;p&gt;Text here&lt;/p&gt;</td></tr>\n                <tr><td class=\"p-3 font-mono\">&lt;a&gt;</td><td class=\"p-3\">Link</td><td class=\"p-3 font-mono\">&lt;a href=\"url\"&gt;Click&lt;/a&gt;</td></tr>\n                <tr><td class=\"p-3 font-mono\">&lt;img&gt;</td><td class=\"p-3\">Image</td><td class=\"p-3 font-mono\">&lt;img src=\"pic.jpg\"&gt;</td></tr>\n                <tr><td class=\"p-3 font-mono\">&lt;div&gt;</td><td class=\"p-3\">Container</td><td class=\"p-3 font-mono\">&lt;div&gt;Content&lt;/div&gt;</td></tr>\n            </tbody>\n        </table>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Try It Yourself!</h3>\n    <div class=\"p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg\">\n        <p class=\"text-sm mb-2\">💡 <strong>Exercise:</strong> Create a file called <code>index.html</code> and add:</p>\n        <ol class=\"text-sm list-decimal list-inside space-y-1\">\n            <li>A heading with your name</li>\n            <li>A paragraph about yourself</li>\n            <li>A link to your favorite website</li>\n        </ol>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-orange-500 to-red-500 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">📚 Next Up</h4>\n        <p class=\"mb-0\">In the next lesson, we\'ll build a complete webpage from scratch!</p>\n    </div>\n</div>\n',NULL,NULL,2,20,1,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (30,3,'first-webpage','Build Your First Webpage','Hands-on: Create a simple HTML page.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Build Your First Webpage 🚀</h2>\n    \n    <p class=\"text-lg\">Let\'s put everything together and build a real webpage—a personal profile page!</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">The Complete Code</h3>\n    \n    <div class=\"my-6 bg-gray-900 text-gray-100 rounded-xl overflow-hidden\">\n        <div class=\"bg-gray-800 px-4 py-2 text-sm text-gray-400\">profile.html</div>\n        <pre class=\"p-4 overflow-x-auto text-sm\"><code>&lt;!DOCTYPE html&gt;\n&lt;html&gt;\n&lt;head&gt;\n    &lt;title&gt;About Me&lt;/title&gt;\n    &lt;style&gt;\n        body { font-family: Arial; max-width: 600px; margin: 50px auto; }\n        h1 { color: #4F46E5; }\n        .skills { background: #F3F4F6; padding: 20px; border-radius: 10px; }\n    &lt;/style&gt;\n&lt;/head&gt;\n&lt;body&gt;\n    &lt;h1&gt;Hi, I\'m [Your Name]! 👋&lt;/h1&gt;\n    &lt;p&gt;I\'m learning web development and excited to build cool things!&lt;/p&gt;\n    \n    &lt;h2&gt;My Skills&lt;/h2&gt;\n    &lt;div class=\"skills\"&gt;\n        &lt;ul&gt;\n            &lt;li&gt;HTML ✓&lt;/li&gt;\n            &lt;li&gt;CSS (learning)&lt;/li&gt;\n            &lt;li&gt;JavaScript (coming soon)&lt;/li&gt;\n        &lt;/ul&gt;\n    &lt;/div&gt;\n    \n    &lt;h2&gt;Contact Me&lt;/h2&gt;\n    &lt;p&gt;Email: &lt;a href=\"mailto:you@email.com\"&gt;you@email.com&lt;/a&gt;&lt;/p&gt;\n&lt;/body&gt;\n&lt;/html&gt;</code></pre>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">What We Used</h3>\n    <div class=\"grid md:grid-cols-2 gap-4\">\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-green-600 dark:text-green-400\">✅ HTML Elements</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Headings, paragraphs, lists, links</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-blue-600 dark:text-blue-400\">🎨 Basic CSS</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Fonts, colors, spacing, borders</p>\n        </div>\n    </div>\n    \n    <div class=\"mt-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg\">\n        <h4 class=\"font-medium mb-2\">🎉 Congratulations!</h4>\n        <p class=\"text-sm mb-0\">You\'ve built your first webpage! Save this file and open it in your browser to see the result.</p>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gray-100 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600\">\n        <h4 class=\"font-bold text-lg mb-2\">🔓 Keep Learning</h4>\n        <p class=\"text-gray-600 dark:text-gray-400 mb-4\">Upgrade to access 17 more lessons:</p>\n        <ul class=\"text-sm space-y-1 text-gray-600 dark:text-gray-400 mb-4\">\n            <li>• CSS Flexbox & Grid layouts</li>\n            <li>• Responsive design for mobile</li>\n            <li>• JavaScript fundamentals</li>\n            <li>• DOM manipulation</li>\n            <li>• Building interactive features</li>\n        </ul>\n        <a href=\"/courses/pricing\" class=\"inline-block bg-green-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-green-700 transition-colors\">\n            Upgrade Now →\n        </a>\n    </div>\n</div>\n',NULL,NULL,3,25,1,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (31,3,'html-elements','HTML Elements Deep Dive','Headings, paragraphs, lists, and links.','text',NULL,NULL,NULL,4,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (32,3,'html-forms','HTML Forms and Input','Create interactive forms for user input.','text',NULL,NULL,NULL,5,18,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (33,3,'css-intro','Introduction to CSS','Adding style to your web pages.','text',NULL,NULL,NULL,6,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (34,3,'css-selectors','CSS Selectors and Properties','Target elements and apply styles.','text',NULL,NULL,NULL,7,18,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (35,3,'css-box-model','The CSS Box Model','Understand padding, margin, and borders.','text',NULL,NULL,NULL,8,15,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (36,3,'css-flexbox','Flexbox Layout','Create flexible, responsive layouts.','text',NULL,NULL,NULL,9,25,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (37,3,'css-grid','CSS Grid Layout','Master two-dimensional layouts.','text',NULL,NULL,NULL,10,25,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (38,3,'responsive-design','Responsive Web Design','Build sites that work on any device.','text',NULL,NULL,NULL,11,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (39,3,'js-intro','Introduction to JavaScript','Add interactivity to your pages.','text',NULL,NULL,NULL,12,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (40,3,'js-variables','Variables and Data Types','Store and manipulate data in JS.','text',NULL,NULL,NULL,13,18,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (41,3,'js-functions','Functions in JavaScript','Create reusable code blocks.','text',NULL,NULL,NULL,14,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (42,3,'js-dom','DOM Manipulation','Change page content with JavaScript.','text',NULL,NULL,NULL,15,25,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (43,3,'js-events','Event Handling','Respond to user interactions.','text',NULL,NULL,NULL,16,20,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (44,3,'project-portfolio','Project: Build a Portfolio','Create your personal portfolio site.','text',NULL,NULL,NULL,17,45,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (45,3,'debugging','Debugging and Dev Tools','Find and fix issues in your code.','text',NULL,NULL,NULL,18,15,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (46,3,'best-practices','Web Development Best Practices','Write clean, maintainable code.','text',NULL,NULL,NULL,19,12,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (47,3,'next-steps','Next Steps in Your Journey','Frameworks, libraries, and career paths.','text',NULL,NULL,NULL,20,10,0,1,NULL,'2025-12-15 06:00:47','2025-12-15 06:00:47');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (48,4,'agentic-marketing-intro','What is Agentic Marketing?','Understanding AI agents in digital marketing.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">What is Agentic Marketing? 🤖📈</h2>\n    \n    <div class=\"bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/30 rounded-xl p-6 mb-6\">\n        <p class=\"text-lg mb-0\"><strong>Agentic Marketing</strong> uses AI agents to automate, optimize, and scale your marketing efforts. It\'s the future of digital marketing!</p>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Traditional vs Agentic Marketing</h3>\n    \n    <div class=\"grid md:grid-cols-2 gap-6 my-6\">\n        <div class=\"p-5 bg-gray-100 dark:bg-gray-800 rounded-xl\">\n            <h4 class=\"font-bold text-red-600 dark:text-red-400 mb-3\">❌ Traditional Marketing</h4>\n            <ul class=\"text-sm space-y-2\">\n                <li>Manual content creation</li>\n                <li>Hours of data analysis</li>\n                <li>One-size-fits-all campaigns</li>\n                <li>Limited scale</li>\n                <li>Reactive decisions</li>\n            </ul>\n        </div>\n        <div class=\"p-5 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800\">\n            <h4 class=\"font-bold text-green-600 dark:text-green-400 mb-3\">✅ Agentic Marketing</h4>\n            <ul class=\"text-sm space-y-2\">\n                <li>AI-generated content at scale</li>\n                <li>Real-time analytics & insights</li>\n                <li>Personalized customer journeys</li>\n                <li>Unlimited scalability</li>\n                <li>Predictive optimization</li>\n            </ul>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">What AI Agents Can Do</h3>\n    <div class=\"space-y-3\">\n        <div class=\"flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg\">\n            <span class=\"text-xl\">✍️</span>\n            <span><strong>Content Creation:</strong> Write emails, social posts, ad copy in seconds</span>\n        </div>\n        <div class=\"flex items-center gap-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg\">\n            <span class=\"text-xl\">🎯</span>\n            <span><strong>Audience Targeting:</strong> Find and segment ideal customers automatically</span>\n        </div>\n        <div class=\"flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg\">\n            <span class=\"text-xl\">📊</span>\n            <span><strong>Performance Analysis:</strong> Track and optimize campaigns 24/7</span>\n        </div>\n        <div class=\"flex items-center gap-3 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg\">\n            <span class=\"text-xl\">🔄</span>\n            <span><strong>A/B Testing:</strong> Continuously test and improve at scale</span>\n        </div>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">🚀 The Future is Here</h4>\n        <p class=\"mb-0\">Companies using AI marketing see 30-50% improvement in conversion rates. Ready to learn how?</p>\n    </div>\n</div>\n',NULL,NULL,1,15,1,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (49,4,'ai-tools-overview','AI Marketing Tools Landscape','Overview of available AI marketing tools.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">AI Marketing Tools Landscape 🛠️</h2>\n    \n    <p class=\"text-lg\">The AI marketing ecosystem is exploding with powerful tools. Here\'s your guide to the most impactful ones.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Content Creation</h3>\n    <div class=\"grid md:grid-cols-2 gap-4 my-4\">\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-purple-600 dark:text-purple-400\">ChatGPT / Claude</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">General-purpose content, emails, scripts</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-blue-600 dark:text-blue-400\">Jasper</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Marketing-focused AI copywriting</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-pink-600 dark:text-pink-400\">Midjourney / DALL-E</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">AI image generation for ads & social</p>\n        </div>\n        <div class=\"p-4 bg-gray-100 dark:bg-gray-800 rounded-lg\">\n            <h4 class=\"font-medium text-green-600 dark:text-green-400\">Copy.ai</h4>\n            <p class=\"text-sm text-gray-600 dark:text-gray-400\">Ad copy, product descriptions</p>\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Analytics & Optimization</h3>\n    <div class=\"space-y-2\">\n        <div class=\"p-3 border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20\">\n            <strong>Google Analytics 4:</strong> AI-powered insights and predictions\n        </div>\n        <div class=\"p-3 border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20\">\n            <strong>HubSpot:</strong> AI-driven CRM and marketing automation\n        </div>\n        <div class=\"p-3 border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20\">\n            <strong>Hootsuite / Buffer:</strong> AI scheduling and analytics for social\n        </div>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Must-Have for 2025</h3>\n    <div class=\"p-4 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/30 rounded-lg\">\n        <p class=\"text-sm mb-0\">🔥 <strong>Custom AI Agents:</strong> Tools like AutoGPT and AgentGPT let you build autonomous marketing agents that work while you sleep!</p>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl\">\n        <h4 class=\"text-lg font-bold mb-2\">💬 Try AI Marketing Now</h4>\n        <p class=\"mb-4\">Use our AI chat to generate marketing content!</p>\n        <a href=\"/chat\" class=\"inline-block bg-white text-purple-600 font-semibold px-6 py-3 rounded-lg hover:bg-purple-50 transition-colors\">\n            Open AI Chat →\n        </a>\n    </div>\n</div>\n',NULL,NULL,2,12,1,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (50,4,'setting-up-agents','Setting Up Your First AI Agent','Practical guide to configuring AI agents.','text','\n<div class=\"lesson-content prose dark:prose-invert max-w-none\">\n    <h2 class=\"text-2xl font-bold mb-4\">Setting Up Your First AI Agent 🤖</h2>\n    \n    <p class=\"text-lg\">Let\'s create a simple AI marketing agent using ChatGPT. You\'ll learn the fundamentals of prompt engineering for marketing.</p>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Step 1: Define Your Agent\'s Role</h3>\n    <p>Every great AI agent starts with a clear role definition:</p>\n    \n    <div class=\"my-6 bg-gray-900 text-gray-100 rounded-xl overflow-hidden\">\n        <div class=\"bg-gray-800 px-4 py-2 text-sm text-gray-400\">System Prompt Template</div>\n        <pre class=\"p-4 overflow-x-auto text-sm\"><code>You are a marketing expert specializing in [your niche].\nYour goal is to help create compelling [content type].\nAlways maintain a [tone] voice.\nFocus on [key outcome] for the target audience.</code></pre>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Step 2: Create Your First Marketing Agent</h3>\n    <p>Try this email marketing agent prompt:</p>\n    \n    <div class=\"my-6 bg-gray-900 text-gray-100 rounded-xl overflow-hidden\">\n        <div class=\"bg-gray-800 px-4 py-2 text-sm text-gray-400\">Email Agent Prompt</div>\n        <pre class=\"p-4 overflow-x-auto text-sm whitespace-pre-wrap\"><code>You are an email marketing specialist. \n\nFor any product I describe, you will:\n1. Create a compelling subject line (under 50 chars)\n2. Write an engaging opening hook\n3. List 3 key benefits\n4. Include a clear call-to-action\n5. Keep the total under 200 words\n\nTone: Friendly but professional\nGoal: Drive clicks to landing page</code></pre>\n    </div>\n    \n    <h3 class=\"text-xl font-semibold mt-6 mb-3\">Step 3: Test It!</h3>\n    <div class=\"p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg\">\n        <p class=\"text-sm mb-2\">🎯 <strong>Try this:</strong></p>\n        <p class=\"text-sm mb-0\">Go to our AI chat and paste the agent prompt above, then describe a product. Watch the magic happen!</p>\n    </div>\n    \n    <div class=\"mt-8 p-6 bg-gray-100 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600\">\n        <h4 class=\"font-bold text-lg mb-2\">🔓 Master AI Marketing</h4>\n        <p class=\"text-gray-600 dark:text-gray-400 mb-4\">Upgrade to access 7 more lessons:</p>\n        <ul class=\"text-sm space-y-1 text-gray-600 dark:text-gray-400 mb-4\">\n            <li>• Advanced prompt engineering</li>\n            <li>• Multi-channel campaign automation</li>\n            <li>• AI-powered analytics</li>\n            <li>• Customer journey mapping</li>\n            <li>• Building autonomous agents</li>\n        </ul>\n        <a href=\"/courses/pricing\" class=\"inline-block bg-orange-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-orange-700 transition-colors\">\n            Upgrade Now →\n        </a>\n    </div>\n</div>\n',NULL,NULL,3,20,1,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:32:40');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (51,4,'content-automation','Automating Content Creation','Use AI to generate marketing content at scale.','text',NULL,NULL,NULL,4,25,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (52,4,'audience-analysis','AI-Powered Audience Analysis','Understand your audience with AI insights.','text',NULL,NULL,NULL,5,20,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (53,4,'campaign-optimization','Campaign Optimization with AI','Let AI optimize your ad campaigns.','text',NULL,NULL,NULL,6,22,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (54,4,'social-media-agents','Social Media AI Agents','Automate social media management.','text',NULL,NULL,NULL,7,18,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (55,4,'email-personalization','AI Email Personalization','Create personalized email campaigns.','text',NULL,NULL,NULL,8,20,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (56,4,'analytics-insights','AI Analytics and Reporting','Generate insights from marketing data.','text',NULL,NULL,NULL,9,18,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
INSERT IGNORE INTO `lessons` (`id`, `course_id`, `slug`, `title`, `description`, `content_type`, `content_html`, `content_url`, `embed_file`, `lesson_order`, `duration_minutes`, `is_free_preview`, `is_published`, `resources`, `created_at`, `updated_at`) VALUES (57,4,'scaling-strategies','Scaling Your AI Marketing','Strategies for growth and ROI measurement.','text',NULL,NULL,NULL,10,15,0,1,NULL,'2025-12-15 06:01:12','2025-12-15 06:01:12');
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `achievements`
--

LOCK TABLES `achievements` WRITE;
/*!40000 ALTER TABLE `achievements` DISABLE KEYS */;
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (1,'first_lesson','First Steps','Complete your first lesson','fa-shoe-prints',10,NULL,'2025-12-15 05:46:17');
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (2,'speed_demon','Speed Demon','Reach 60 WPM in touch typing','fa-bolt',50,NULL,'2025-12-15 05:46:17');
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (3,'course_complete','Course Graduate','Complete any course','fa-graduation-cap',100,NULL,'2025-12-15 05:46:17');
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (4,'streak_7','Week Warrior','Study 7 days in a row','fa-fire',25,NULL,'2025-12-15 05:46:17');
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (5,'streak_30','Monthly Master','Study 30 days in a row','fa-crown',100,NULL,'2025-12-15 05:46:17');
INSERT IGNORE INTO `achievements` (`id`, `slug`, `name`, `description`, `icon`, `points`, `criteria`, `created_at`) VALUES (6,'helper','Community Helper','Help 10 other students','fa-hands-helping',50,NULL,'2025-12-15 05:46:17');
/*!40000 ALTER TABLE `achievements` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-15 20:28:07
