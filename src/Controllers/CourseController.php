<?php

namespace Ginto\Controllers;

use PDO;
use Medoo\Medoo;
use Ginto\Helpers\TransactionHelper;

/**
 * CourseController - Handles course operations, lessons, progress tracking
 */
class CourseController
{
    private PDO $db;
    
    public function __construct(PDO|Medoo $db)
    {
        // If Medoo is passed, extract the underlying PDO connection
        if ($db instanceof Medoo) {
            $this->db = $db->pdo;
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Get all courses with category info
     */
    public function getAllCourses(bool $publishedOnly = true): array
    {
        $sql = "SELECT c.*, cc.name as category_name, cc.slug as category_slug, cc.icon as category_icon, cc.color as category_color
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id";
        
        if ($publishedOnly) {
            $sql .= " WHERE c.is_published = 1";
        }
        
        $sql .= " ORDER BY c.is_featured DESC, c.created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get courses by category
     */
    public function getCoursesByCategory(string $categorySlug): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, cc.name as category_name, cc.slug as category_slug, cc.icon as category_icon, cc.color as category_color
            FROM courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            WHERE cc.slug = ? AND c.is_published = 1
            ORDER BY c.is_featured DESC, c.created_at DESC
        ");
        $stmt->execute([$categorySlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single course by slug
     */
    public function getCourseBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, cc.name as category_name, cc.slug as category_slug, cc.icon as category_icon, cc.color as category_color
            FROM courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            WHERE c.slug = ?
        ");
        $stmt->execute([$slug]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        return $course ?: null;
    }
    
    /**
     * Get all lessons for a course
     */
    public function getCourseLessons(int $courseId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lessons
            WHERE course_id = ? AND is_published = 1
            ORDER BY lesson_order ASC
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lesson by slug
     */
    public function getLessonBySlug(int $courseId, string $lessonSlug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lessons
            WHERE course_id = ? AND slug = ? AND is_published = 1
        ");
        $stmt->execute([$courseId, $lessonSlug]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query("
            SELECT cc.*, COUNT(c.id) as course_count
            FROM course_categories cc
            LEFT JOIN courses c ON cc.id = c.category_id AND c.is_published = 1
            WHERE cc.is_active = 1
            GROUP BY cc.id
            ORDER BY cc.sort_order ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all subscription plans for a specific type
     * @param string $planType 'courses' or 'masterclass'
     */
    public function getSubscriptionPlans(string $planType = 'courses'): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM subscription_plans
            WHERE is_active = 1 AND plan_type = ?
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$planType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's current subscription
     */
    public function getUserSubscription(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT us.*, sp.name as plan_name, sp.display_name as plan_display_name, 
                   sp.max_lessons_per_course, sp.max_courses, sp.has_ai_tutor, sp.has_certificates
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? AND us.status = 'active' AND (us.expires_at IS NULL OR us.expires_at > NOW())
            ORDER BY us.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        return $sub ?: null;
    }
    
    /**
     * Get user's plan name (for access control)
     */
    public function getUserPlanName(int $userId): string
    {
        $subscription = $this->getUserSubscription($userId);
        return $subscription['plan_name'] ?? 'free';
    }
    
    /**
     * Check if user can access a specific lesson
     */
    public function canAccessLesson(int $userId, array $lesson, ?string $userPlan = null): bool
    {
        // Free preview lessons are always accessible
        if ($lesson['is_free_preview']) {
            return true;
        }
        
        // Non-logged in users can only access free previews
        if ($userId <= 0) {
            return false;
        }
        
        // Get user's plan if not provided
        if ($userPlan === null) {
            $userPlan = $this->getUserPlanName($userId);
        }
        
        // Get course info
        $stmt = $this->db->prepare("SELECT min_plan_required FROM courses WHERE id = ?");
        $stmt->execute([$lesson['course_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Plan hierarchy
        $planHierarchy = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        $userPlanLevel = $planHierarchy[$userPlan] ?? 1;
        $requiredPlanLevel = $planHierarchy[$course['min_plan_required'] ?? 'free'] ?? 1;
        
        // Check if user's plan meets minimum requirement
        if ($userPlanLevel < $requiredPlanLevel) {
            return false;
        }
        
        // Check lesson limits for go plan
        if ($userPlan === 'go') {
            $lessonOrder = $lesson['lesson_order'] ?? PHP_INT_MAX;
            if ($lessonOrder > 10) {
                return false;
            }
        }
        
        // Check lesson limits for free plan
        if ($userPlan === 'free') {
            $lessonOrder = $lesson['lesson_order'] ?? PHP_INT_MAX;
            if ($lessonOrder > 3) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get lessons accessible to user for a course
     */
    public function getAccessibleLessons(int $userId, int $courseId): array
    {
        $lessons = $this->getCourseLessons($courseId);
        $userPlan = $userId > 0 ? $this->getUserPlanName($userId) : 'free';
        
        foreach ($lessons as &$lesson) {
            $lesson['is_accessible'] = $this->canAccessLesson($userId, $lesson, $userPlan);
            $lesson['requires_upgrade'] = !$lesson['is_accessible'] && !$lesson['is_free_preview'];
        }
        
        return $lessons;
    }
    
    /**
     * Enroll user in a course
     */
    public function enrollUser(int $userId, int $courseId): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO course_enrollments (user_id, course_id, enrolled_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_accessed_at = NOW()
        ");
        return $stmt->execute([$userId, $courseId]);
    }
    
    /**
     * Get user's enrollment for a course
     */
    public function getUserEnrollment(int $userId, int $courseId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM course_enrollments
            WHERE user_id = ? AND course_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $enrollment ?: null;
    }
    
    /**
     * Update lesson progress
     */
    public function updateLessonProgress(int $userId, int $lessonId, int $courseId, string $status = 'completed', int $timeSpent = 0): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO lesson_progress (user_id, lesson_id, course_id, status, started_at, completed_at, time_spent_seconds)
            VALUES (?, ?, ?, ?, NOW(), IF(?='completed', NOW(), NULL), ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                completed_at = IF(VALUES(status)='completed' AND completed_at IS NULL, NOW(), completed_at),
                time_spent_seconds = time_spent_seconds + VALUES(time_spent_seconds),
                updated_at = NOW()
        ");
        $result = $stmt->execute([$userId, $lessonId, $courseId, $status, $status, $timeSpent]);
        
        // Update course progress
        $this->updateCourseProgress($userId, $courseId);
        
        return $result;
    }
    
    /**
     * Update course progress percentage
     */
    private function updateCourseProgress(int $userId, int $courseId): void
    {
        // Get total lessons and completed lessons
        $stmt = $this->db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM lessons WHERE course_id = ? AND is_published = 1) as total,
                (SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = ? AND status = 'completed') as completed
        ");
        $stmt->execute([$courseId, $userId, $courseId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $progress = $counts['total'] > 0 ? ($counts['completed'] / $counts['total']) * 100 : 0;
        
        $stmt = $this->db->prepare("
            UPDATE course_enrollments 
            SET progress_percent = ?, 
                completed_at = IF(? >= 100, NOW(), NULL),
                updated_at = NOW()
            WHERE user_id = ? AND course_id = ?
        ");
        $stmt->execute([$progress, $progress, $userId, $courseId]);
    }
    
    /**
     * Get user's lesson progress
     */
    public function getLessonProgress(int $userId, int $lessonId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lesson_progress
            WHERE user_id = ? AND lesson_id = ?
        ");
        $stmt->execute([$userId, $lessonId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        return $progress ?: null;
    }
    
    /**
     * Get user's progress for all lessons in a course
     */
    public function getCourseProgressDetails(int $userId, int $courseId): array
    {
        $stmt = $this->db->prepare("
            SELECT lesson_id, status, completed_at, time_spent_seconds
            FROM lesson_progress
            WHERE user_id = ? AND course_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($progress as $p) {
            $result[$p['lesson_id']] = $p;
        }
        return $result;
    }
    
    /**
     * Get user's enrolled courses
     */
    public function getUserEnrolledCourses(int $userId, ?string $status = null): array
    {
        $sql = "SELECT ce.*, c.*, cc.name as category_name, cc.slug as category_slug
                FROM course_enrollments ce
                JOIN courses c ON ce.course_id = c.id
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                WHERE ce.user_id = ?";
        
        $params = [$userId];
        
        if ($status === 'progress') {
            $sql .= " AND ce.progress_percent > 0 AND ce.progress_percent < 100";
        } elseif ($status === 'completed') {
            $sql .= " AND ce.completed_at IS NOT NULL";
        } elseif ($status === 'bookmarked') {
            $sql .= " AND ce.is_bookmarked = 1";
        }
        
        $sql .= " ORDER BY ce.last_accessed_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Toggle bookmark for a course
     */
    public function toggleBookmark(int $userId, int $courseId): bool
    {
        // First ensure enrollment exists
        $this->enrollUser($userId, $courseId);
        
        $stmt = $this->db->prepare("
            UPDATE course_enrollments 
            SET is_bookmarked = NOT is_bookmarked,
                updated_at = NOW()
            WHERE user_id = ? AND course_id = ?
        ");
        return $stmt->execute([$userId, $courseId]);
    }
    
    /**
     * Get course statistics
     */
    public function getCourseStats(int $courseId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT ce.user_id) as total_students,
                AVG(ce.progress_percent) as avg_progress,
                COUNT(DISTINCT CASE WHEN ce.completed_at IS NOT NULL THEN ce.user_id END) as completions,
                AVG(cr.rating) as avg_rating,
                COUNT(cr.id) as review_count
            FROM courses c
            LEFT JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN course_reviews cr ON c.id = cr.course_id AND cr.is_approved = 1
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_students' => 0,
            'avg_progress' => 0,
            'completions' => 0,
            'avg_rating' => null,
            'review_count' => 0
        ];
    }
    
    /**
     * Search courses
     */
    public function searchCourses(string $query): array
    {
        $searchTerm = '%' . $query . '%';
        $stmt = $this->db->prepare("
            SELECT c.*, cc.name as category_name, cc.slug as category_slug, cc.icon as category_icon, cc.color as category_color
            FROM courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            WHERE c.is_published = 1 AND (
                c.title LIKE ? OR 
                c.description LIKE ? OR 
                c.subtitle LIKE ? OR
                JSON_SEARCH(c.tags, 'one', ?) IS NOT NULL
            )
            ORDER BY c.is_featured DESC, c.title ASC
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $query]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get next lesson in course
     */
    public function getNextLesson(int $courseId, int $currentLessonOrder): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lessons
            WHERE course_id = ? AND lesson_order > ? AND is_published = 1
            ORDER BY lesson_order ASC
            LIMIT 1
        ");
        $stmt->execute([$courseId, $currentLessonOrder]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Get previous lesson in course
     */
    public function getPreviousLesson(int $courseId, int $currentLessonOrder): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lessons
            WHERE course_id = ? AND lesson_order < ? AND is_published = 1
            ORDER BY lesson_order DESC
            LIMIT 1
        ");
        $stmt->execute([$courseId, $currentLessonOrder]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Create a new subscription for user
     */
    public function createSubscription(int $userId, int $planId, string $paymentMethod, float $amount): int
    {
        $this->db->beginTransaction();
        
        try {
            // Cancel any existing active subscription
            $stmt = $this->db->prepare("
                UPDATE user_subscriptions 
                SET status = 'cancelled', cancelled_at = NOW()
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            
            // Create new subscription
            $stmt = $this->db->prepare("
                INSERT INTO user_subscriptions (user_id, plan_id, status, started_at, expires_at, payment_method, amount_paid)
                VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), ?, ?)
            ");
            $stmt->execute([$userId, $planId, $paymentMethod, $amount]);
            $subscriptionId = (int)$this->db->lastInsertId();
            
            // Record payment
            $transactionId = TransactionHelper::generateTransactionId();
            $stmt = $this->db->prepare("
                INSERT INTO subscription_payments (user_id, subscription_id, plan_id, amount, payment_method, status, paid_at, transaction_id)
                VALUES (?, ?, ?, ?, ?, 'completed', NOW(), ?)
            ");
            $stmt->execute([$userId, $subscriptionId, $planId, $amount, $paymentMethod, $transactionId]);
            
            $this->db->commit();
            return $subscriptionId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get user's study streak info
     */
    public function getStudyStreak(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM study_streaks WHERE user_id = ?");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$streak) {
            return [
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_study_days' => 0,
                'last_study_date' => null
            ];
        }
        
        return $streak;
    }
    
    /**
     * Update study streak when user completes a lesson
     */
    public function updateStudyStreak(int $userId): void
    {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("SELECT * FROM study_streaks WHERE user_id = ?");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$streak) {
            // First time studying
            $stmt = $this->db->prepare("
                INSERT INTO study_streaks (user_id, current_streak, longest_streak, last_study_date, total_study_days)
                VALUES (?, 1, 1, ?, 1)
            ");
            $stmt->execute([$userId, $today]);
            return;
        }
        
        // Already studied today
        if ($streak['last_study_date'] === $today) {
            return;
        }
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if ($streak['last_study_date'] === $yesterday) {
            // Continuing streak
            $newStreak = $streak['current_streak'] + 1;
            $longestStreak = max($newStreak, $streak['longest_streak']);
        } else {
            // Streak broken, start fresh
            $newStreak = 1;
            $longestStreak = $streak['longest_streak'];
        }
        
        $stmt = $this->db->prepare("
            UPDATE study_streaks 
            SET current_streak = ?, longest_streak = ?, last_study_date = ?, total_study_days = total_study_days + 1
            WHERE user_id = ?
        ");
        $stmt->execute([$newStreak, $longestStreak, $today, $userId]);
    }

    /**
     * Courses listing page
     */
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        $isLoggedIn = !empty($_SESSION['user_id']);
        $isAdmin = \Ginto\Controllers\UserController::isAdmin();
        $username = $_SESSION['username'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
        
        $courses = $this->getAllCourses();
        $categories = $this->getCategories();
        $userPlan = $isLoggedIn ? $this->getUserPlanName($userId) : 'free';
        
        // Handle category filter
        $categoryFilter = $_GET['category'] ?? null;
        if ($categoryFilter) {
            $courses = $this->getCoursesByCategory($categoryFilter);
        }
        
        // Handle user learning status filter
        $statusFilter = $_GET['status'] ?? null;
        $enrolledCourses = [];
        if ($isLoggedIn && $statusFilter) {
            $enrolledCourses = $this->getUserEnrolledCourses($userId, $statusFilter);
        }
        
        \Ginto\Core\View::view('courses/courses', [
            'title' => 'Courses',
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'username' => $username,
            'userId' => $userId,
            'userFullname' => $userFullname,
            'courses' => $courses,
            'categories' => $categories,
            'userPlan' => $userPlan,
            'categoryFilter' => $categoryFilter,
            'statusFilter' => $statusFilter,
            'enrolledCourses' => $enrolledCourses,
        ]);
    }

    /**
     * Pricing page for courses
     */
    public function pricing(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        
        $isLoggedIn = !empty($_SESSION['user_id']);
        $userId = $_SESSION['user_id'] ?? 0;
        
        $plans = $this->getSubscriptionPlans('courses');
        $currentPlan = $isLoggedIn ? $this->getUserPlanName($userId) : 'free';
        
        \Ginto\Core\View::view('courses/pricing', [
            'title' => 'Pricing | Ginto Courses',
            'isLoggedIn' => $isLoggedIn,
            'plans' => $plans,
            'currentPlan' => $currentPlan,
        ]);
    }
}
