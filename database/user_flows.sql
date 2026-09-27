CREATE TABLE IF NOT EXISTS eligibility_checks (
    eligibility_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outcome ENUM('Eligible', 'Not Eligible', 'Needs Review') NOT NULL,
    answers_json TEXT NOT NULL,
    reasons_json TEXT NOT NULL,
    completed_at DATETIME NOT NULL,
    INDEX idx_eligibility_user (user_id, eligibility_id),
    CONSTRAINT fk_eligibility_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaign_registrations (
    registration_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    eligibility_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    email VARCHAR(100) NOT NULL,
    requirements_agreed_at DATETIME NOT NULL,
    CONSTRAINT fk_registration_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
    CONSTRAINT fk_registration_eligibility FOREIGN KEY (eligibility_id) REFERENCES eligibility_checks(eligibility_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
    subscription_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    subscription_status ENUM('Pending', 'Subscribed') NOT NULL DEFAULT 'Pending',
    token_hash CHAR(64) DEFAULT NULL UNIQUE,
    token_expires_at DATETIME DEFAULT NULL,
    delivery_status ENUM('Pending', 'Preview', 'Accepted', 'Failed') NOT NULL DEFAULT 'Pending',
    requested_at DATETIME NOT NULL,
    confirmed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message_body TEXT NOT NULL,
    message_status ENUM('New', 'Replied') NOT NULL DEFAULT 'New',
    received_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
