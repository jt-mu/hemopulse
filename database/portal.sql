CREATE TABLE IF NOT EXISTS campaign_categories (
 category_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS login_attempts (
 attempt_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identity_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL,
 INDEX idx_login_window(identity_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
