-- Migration: Set live PayPal plan ID for ImageGen (idempotent)
-- Created: 2026-01-25
-- Purpose: If the live `paypal_plan_id` is missing for the `imagegen` addon,
--          copy the sandbox plan id (`paypal_plan_id_sandbox`) into the live
--          column so the UI and activation flow work for unsandboxed envs.
-- IMPORTANT: Backup your DB before applying.

UPDATE `addon_plans`
SET `paypal_plan_id` = `paypal_plan_id_sandbox`,
    `updated_at` = NOW()
WHERE `addon_type` = 'imagegen'
  AND (`paypal_plan_id` IS NULL OR `paypal_plan_id` = '');

-- This statement is idempotent: it only sets the live plan id when it's empty.
-- Rollback (manual): if needed, set paypal_plan_id = NULL for imagegen
-- DELETE / manual reversal example:
-- UPDATE `addon_plans` SET `paypal_plan_id` = NULL WHERE `addon_type` = 'imagegen';
