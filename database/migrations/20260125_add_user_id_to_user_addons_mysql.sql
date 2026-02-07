-- Migration: ensure user_addons has a user_id column and index
-- Adds user_id column if missing and an index for faster lookups

ALTER TABLE `user_addons` 
  ADD COLUMN IF NOT EXISTS `user_id` INT NULL AFTER `id`;

-- Add index on user_id if not exists
CREATE INDEX IF NOT EXISTS `idx_user_addons_user_id` ON `user_addons`(`user_id`);

-- Optional: add foreign key if users table and constraint do not exist
-- Note: adding FK can fail on some installations; enable manually if desired
-- ALTER TABLE `user_addons` 
--   ADD CONSTRAINT `user_addons_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- End migration
