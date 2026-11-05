-- Migration: Add plan_type column to subscription_plans
-- This allows separate pricing for courses vs masterclasses
-- Masterclass plans are 2x the price of course plans

-- Add plan_type column (courses, masterclass)
ALTER TABLE subscription_plans 
ADD COLUMN plan_type ENUM('courses', 'masterclass') NOT NULL DEFAULT 'courses' AFTER name;

-- Drop the unique constraint on name so we can have same names for different plan types
ALTER TABLE subscription_plans DROP INDEX name;

-- Add unique constraint on name + plan_type combination
ALTER TABLE subscription_plans ADD UNIQUE INDEX idx_name_plan_type (name, plan_type);

-- Insert masterclass plans (2x pricing)
INSERT INTO subscription_plans 
(name, plan_type, display_name, price_monthly, price_currency, description, features, max_lessons_per_course, max_courses, has_ai_tutor, has_certificates, has_priority_support, badge_color, sort_order, is_active)
VALUES 
('free', 'masterclass', 'Free', 0.00, 'PHP', 
 'Preview masterclass content', 
 '["Access to 1 free lesson per masterclass", "Basic masterclass previews", "Community forum access"]',
 1, NULL, 0, 0, 0, 'gray', 1, 1),

('go', 'masterclass', 'Go', 500.00, 'PHP', 
 'Learn from industry experts', 
 '["Access to 5 lessons per masterclass", "AI-powered study assistance", "Practice exercises", "Progress tracking", "Mobile access"]',
 5, NULL, 0, 0, 0, 'green', 2, 1),

('plus', 'masterclass', 'Plus', 2200.00, 'PHP', 
 'Master advanced skills with experts', 
 '["Unlimited access to all masterclass lessons", "Advanced AI tutor", "Personalized learning paths", "Masterclass certificates", "Priority support", "Live Q&A with experts"]',
 NULL, NULL, 1, 1, 1, 'purple', 3, 1),

('pro', 'masterclass', 'Pro', 19990.00, 'PHP', 
 'Elite masterclass experience', 
 '["Everything in Plus", "1-on-1 mentorship with instructors", "Career guidance", "Exclusive Pro community", "Early access to new masterclasses", "Custom learning plans", "Direct instructor feedback"]',
 NULL, NULL, 1, 1, 1, 'indigo', 4, 1);

-- Update existing plans to be explicitly 'courses' type (they already default to courses)
UPDATE subscription_plans SET plan_type = 'courses' WHERE plan_type = 'courses';
