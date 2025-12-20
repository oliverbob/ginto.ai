<?php

namespace Ginto\Controllers;

use PDO;
use Medoo\Medoo;

/**
 * MasterclassController - Handles masterclass operations, lessons, progress tracking
 * 
 * Masterclasses are in-depth technical training focusing on specific technologies
 * like Redis, LXC/LXD, Docker, Proxmox, Virtualmin, and the Ginto AI platform.
 */
class MasterclassController
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
     * Get all masterclasses with category info
     */
    public function getAllMasterclasses(bool $publishedOnly = true): array
    {
        $sql = "SELECT m.*, mc.name as category_name, mc.slug as category_slug, mc.icon as category_icon, mc.color as category_color
                FROM masterclasses m
                LEFT JOIN masterclass_categories mc ON m.category_id = mc.id";
        
        if ($publishedOnly) {
            $sql .= " WHERE m.is_published = 1";
        }
        
        $sql .= " ORDER BY m.is_featured DESC, m.created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get masterclasses by category
     */
    public function getMasterclassesByCategory(string $categorySlug): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, mc.name as category_name, mc.slug as category_slug, mc.icon as category_icon, mc.color as category_color
            FROM masterclasses m
            LEFT JOIN masterclass_categories mc ON m.category_id = mc.id
            WHERE mc.slug = ? AND m.is_published = 1
            ORDER BY m.is_featured DESC, m.created_at DESC
        ");
        $stmt->execute([$categorySlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single masterclass by slug
     */
    public function getMasterclassBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, mc.name as category_name, mc.slug as category_slug, mc.icon as category_icon, mc.color as category_color
            FROM masterclasses m
            LEFT JOIN masterclass_categories mc ON m.category_id = mc.id
            WHERE m.slug = ?
        ");
        $stmt->execute([$slug]);
        $masterclass = $stmt->fetch(PDO::FETCH_ASSOC);
        return $masterclass ?: null;
    }
    
    /**
     * Get all lessons for a masterclass
     */
    public function getMasterclassLessons(int $masterclassId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM masterclass_lessons
            WHERE masterclass_id = ? AND is_published = 1
            ORDER BY lesson_order ASC
        ");
        $stmt->execute([$masterclassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lesson by slug
     */
    public function getLessonBySlug(int $masterclassId, string $lessonSlug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM masterclass_lessons
            WHERE masterclass_id = ? AND slug = ? AND is_published = 1
        ");
        $stmt->execute([$masterclassId, $lessonSlug]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query("
            SELECT mc.*, COUNT(m.id) as masterclass_count
            FROM masterclass_categories mc
            LEFT JOIN masterclasses m ON mc.id = m.category_id AND m.is_published = 1
            WHERE mc.is_active = 1
            GROUP BY mc.id
            ORDER BY mc.sort_order ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all subscription plans for masterclasses (2x pricing of courses)
     */
    public function getSubscriptionPlans(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM subscription_plans
            WHERE is_active = 1 AND plan_type = 'masterclass'
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's plan name (for access control) - reuses course subscription system
     */
    public function getUserPlanName(int $userId): string
    {
        $stmt = $this->db->prepare("
            SELECT sp.name
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? AND us.status = 'active' AND (us.expires_at IS NULL OR us.expires_at > NOW())
            ORDER BY us.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['name'] ?? 'free';
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
        
        // Get masterclass info
        $stmt = $this->db->prepare("SELECT min_plan_required FROM masterclasses WHERE id = ?");
        $stmt->execute([$lesson['masterclass_id']]);
        $masterclass = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Plan hierarchy
        $planHierarchy = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        $userPlanLevel = $planHierarchy[$userPlan] ?? 1;
        $requiredPlanLevel = $planHierarchy[$masterclass['min_plan_required'] ?? 'free'] ?? 1;
        
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
        
        // Check lesson limits for free plan (first 2 lessons free for masterclasses)
        if ($userPlan === 'free') {
            $lessonOrder = $lesson['lesson_order'] ?? PHP_INT_MAX;
            if ($lessonOrder > 2) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get lessons accessible to user for a masterclass
     */
    public function getAccessibleLessons(int $userId, int $masterclassId): array
    {
        $lessons = $this->getMasterclassLessons($masterclassId);
        $userPlan = $userId > 0 ? $this->getUserPlanName($userId) : 'free';
        
        foreach ($lessons as &$lesson) {
            $lesson['is_accessible'] = $this->canAccessLesson($userId, $lesson, $userPlan);
            $lesson['requires_upgrade'] = !$lesson['is_accessible'] && !$lesson['is_free_preview'];
        }
        
        return $lessons;
    }
    
    /**
     * Enroll user in a masterclass
     */
    public function enrollUser(int $userId, int $masterclassId): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO masterclass_enrollments (user_id, masterclass_id, enrolled_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_accessed_at = NOW()
        ");
        return $stmt->execute([$userId, $masterclassId]);
    }
    
    /**
     * Get user's enrollment for a masterclass
     */
    public function getUserEnrollment(int $userId, int $masterclassId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM masterclass_enrollments
            WHERE user_id = ? AND masterclass_id = ?
        ");
        $stmt->execute([$userId, $masterclassId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $enrollment ?: null;
    }
    
    /**
     * Update lesson progress
     */
    public function updateLessonProgress(int $userId, int $lessonId, int $masterclassId, string $status = 'in_progress'): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO masterclass_lesson_progress (user_id, lesson_id, masterclass_id, status, started_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                completed_at = CASE WHEN VALUES(status) = 'completed' THEN NOW() ELSE completed_at END
        ");
        $result = $stmt->execute([$userId, $lessonId, $masterclassId, $status]);
        
        // Update enrollment progress
        if ($result) {
            $this->updateEnrollmentProgress($userId, $masterclassId);
        }
        
        return $result;
    }
    
    /**
     * Update overall enrollment progress
     */
    private function updateEnrollmentProgress(int $userId, int $masterclassId): void
    {
        $stmt = $this->db->prepare("
            UPDATE masterclass_enrollments me
            SET progress_percent = (
                SELECT ROUND(
                    (COUNT(CASE WHEN mlp.status = 'completed' THEN 1 END) * 100.0) / 
                    NULLIF((SELECT total_lessons FROM masterclasses WHERE id = ?), 0)
                , 2)
                FROM masterclass_lesson_progress mlp
                WHERE mlp.user_id = ? AND mlp.masterclass_id = ?
            ),
            started_at = COALESCE(started_at, NOW()),
            completed_at = CASE 
                WHEN progress_percent = 100 THEN NOW() 
                ELSE NULL 
            END
            WHERE me.user_id = ? AND me.masterclass_id = ?
        ");
        $stmt->execute([$masterclassId, $userId, $masterclassId, $userId, $masterclassId]);
    }
    
    /**
     * Get masterclass progress details for a user
     */
    public function getMasterclassProgressDetails(int $userId, int $masterclassId): array
    {
        $stmt = $this->db->prepare("
            SELECT mlp.*, ml.title as lesson_title, ml.slug as lesson_slug
            FROM masterclass_lesson_progress mlp
            JOIN masterclass_lessons ml ON mlp.lesson_id = ml.id
            WHERE mlp.user_id = ? AND mlp.masterclass_id = ?
            ORDER BY ml.lesson_order ASC
        ");
        $stmt->execute([$userId, $masterclassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get masterclass stats
     */
    public function getMasterclassStats(int $masterclassId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT me.user_id) as total_enrolled,
                COUNT(DISTINCT CASE WHEN me.completed_at IS NOT NULL THEN me.user_id END) as total_completed,
                AVG(me.progress_percent) as avg_progress
            FROM masterclass_enrollments me
            WHERE me.masterclass_id = ?
        ");
        $stmt->execute([$masterclassId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_enrolled' => 0,
            'total_completed' => 0,
            'avg_progress' => 0
        ];
    }
    
    /**
     * Get next lesson in sequence
     */
    public function getNextLesson(int $masterclassId, int $currentOrder): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM masterclass_lessons
            WHERE masterclass_id = ? AND lesson_order > ? AND is_published = 1
            ORDER BY lesson_order ASC
            LIMIT 1
        ");
        $stmt->execute([$masterclassId, $currentOrder]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Get previous lesson in sequence
     */
    public function getPreviousLesson(int $masterclassId, int $currentOrder): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM masterclass_lessons
            WHERE masterclass_id = ? AND lesson_order < ? AND is_published = 1
            ORDER BY lesson_order DESC
            LIMIT 1
        ");
        $stmt->execute([$masterclassId, $currentOrder]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lesson ?: null;
    }
    
    /**
     * Get user's enrolled masterclasses by status
     */
    public function getUserEnrolledMasterclasses(int $userId, string $status): array
    {
        $whereClause = match ($status) {
            'progress' => "me.progress_percent > 0 AND me.progress_percent < 100",
            'completed' => "me.completed_at IS NOT NULL",
            default => "1=1"
        };
        
        $stmt = $this->db->prepare("
            SELECT m.*, mc.name as category_name, mc.slug as category_slug, 
                   mc.icon as category_icon, mc.color as category_color,
                   me.progress_percent, me.enrolled_at, me.completed_at
            FROM masterclass_enrollments me
            JOIN masterclasses m ON me.masterclass_id = m.id
            LEFT JOIN masterclass_categories mc ON m.category_id = mc.id
            WHERE me.user_id = ? AND $whereClause
            ORDER BY me.last_accessed_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get featured masterclasses for homepage
     */
    public function getFeaturedMasterclasses(int $limit = 3): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, mc.name as category_name, mc.slug as category_slug, 
                   mc.icon as category_icon, mc.color as category_color
            FROM masterclasses m
            LEFT JOIN masterclass_categories mc ON m.category_id = mc.id
            WHERE m.is_published = 1 AND m.is_featured = 1
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
