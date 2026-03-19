-- Add shipping and home address JSON fields to users table
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `shipping_address_json` JSON DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `home_address_json` JSON DEFAULT NULL;
