-- Migration: 002_alter_cloud_files_id_bigint_mysql.sql
-- Alters `cloud_files.id` from INT UNSIGNED to BIGINT UNSIGNED to match references
-- Run: mysql -u <user> -p ginto < 002_alter_cloud_files_id_bigint_mysql.sql

ALTER TABLE `cloud_files`
  MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- After running this, foreign key constraints referencing `cloud_files(id)`
-- from columns typed as BIGINT UNSIGNED should be compatible.
