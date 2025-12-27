-- Migration: Rename masterclass plans to Explorer, Builder, Expert, Elite
-- These names are distinct from course plans (free, go, plus, pro)

UPDATE subscription_plans SET 
    name = 'explorer',
    display_name = 'Explorer'
WHERE plan_type = 'masterclass' AND name = 'free';

UPDATE subscription_plans SET 
    name = 'builder',
    display_name = 'Builder'
WHERE plan_type = 'masterclass' AND name = 'go';

UPDATE subscription_plans SET 
    name = 'expert',
    display_name = 'Expert'
WHERE plan_type = 'masterclass' AND name = 'plus';

UPDATE subscription_plans SET 
    name = 'elite',
    display_name = 'Elite'
WHERE plan_type = 'masterclass' AND name = 'pro';
