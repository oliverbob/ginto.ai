-- Add buyer GPS coordinates to users table for precise location persistence
-- Safe to re-run: uses stored procedure for idempotent column addition

DELIMITER //
DROP PROCEDURE IF EXISTS safe_add_col//
CREATE PROCEDURE safe_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN col_def VARCHAR(255), IN after_col VARCHAR(64))
BEGIN
    SET @q = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def, ' AFTER `', after_col, '`');
    SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col);
    IF @c = 0 THEN PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt; END IF;
END//
DELIMITER ;

CALL safe_add_col('users', 'buyer_lat', 'DECIMAL(10,7) DEFAULT NULL', 'buyer_barangay_id');
CALL safe_add_col('users', 'buyer_lng', 'DECIMAL(10,7) DEFAULT NULL', 'buyer_lat');

DROP PROCEDURE IF EXISTS safe_add_col;
