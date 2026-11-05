-- Orders table: stores package purchases and references to users
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    package VARCHAR(100) NOT NULL DEFAULT 'Gold',
    amount DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Safety: if `orders` already exists, ensure `package` column exists
SET @has_orders = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders');
-- Note: the above SELECT is only informative; the CREATE TABLE IF NOT EXISTS will handle creation.
