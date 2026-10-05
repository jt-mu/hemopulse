-- Additive, repeatable migration for MariaDB (XAMPP). No existing records removed.
CREATE TABLE IF NOT EXISTS request_limits (
 limit_key CHAR(64) PRIMARY KEY, window_started BIGINT NOT NULL, attempts INT NOT NULL, last_attempt BIGINT NOT NULL
) ENGINE=InnoDB;
ALTER TABLE users ADD COLUMN IF NOT EXISTS public_reference VARCHAR(28) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uk_user_public_reference ON users(public_reference);
ALTER TABLE eligibility_checks MODIFY outcome ENUM('Eligible','Not Eligible','Needs Review','Temporarily Deferred','Reviewed') NOT NULL,
 ADD COLUMN IF NOT EXISTS deferred_until DATE NULL,
 ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS reviewed_by INT NULL,
 ADD COLUMN IF NOT EXISTS review_notes VARCHAR(2000) NULL;
ALTER TABLE blood_inventory MODIFY inventory_status ENUM('Pending','Available','Reserved','Used','Expired','Discarded','Disposed') NOT NULL DEFAULT 'Pending',
 ADD COLUMN IF NOT EXISTS public_reference VARCHAR(28) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uk_inventory_public_reference ON blood_inventory(public_reference);
ALTER TABLE inventory_transactions MODIFY transaction_type ENUM('Addition','Deduction','Expiration','Disposal','Adjustment') NOT NULL,
 ADD COLUMN IF NOT EXISTS public_reference VARCHAR(28) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uk_transaction_public_reference ON inventory_transactions(public_reference);
ALTER TABLE contact_messages MODIFY message_status ENUM('New','Read','Replied','Closed') NOT NULL DEFAULT 'New';
CREATE TABLE IF NOT EXISTS contact_replies (
 reply_id INT AUTO_INCREMENT PRIMARY KEY, message_id INT NOT NULL, replied_by INT NOT NULL,
 reply_body TEXT NOT NULL, delivery_status VARCHAR(20) NOT NULL, replied_at DATETIME NOT NULL,
 FOREIGN KEY(message_id) REFERENCES contact_messages(message_id), FOREIGN KEY(replied_by) REFERENCES users(user_id)
) ENGINE=InnoDB;
ALTER TABLE newsletter_subscriptions MODIFY subscription_status ENUM('Pending','Subscribed','Unsubscribed') NOT NULL DEFAULT 'Pending',
 ADD COLUMN IF NOT EXISTS unsubscribe_hash CHAR(64) NULL;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS details_json TEXT NULL;
-- Legacy/demo bookings may not contain clinical profile data. Preserve unknowns as NULL.
ALTER TABLE donor_profiles MODIFY blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
 MODIFY date_of_birth DATE NULL, MODIFY weight_kg DECIMAL(5,2) NULL;

ALTER TABLE appointments ADD COLUMN IF NOT EXISTS public_reference VARCHAR(32) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_appointment_reference ON appointments(public_reference);
CREATE TABLE IF NOT EXISTS donor_campaign_selection (
 user_id INT PRIMARY KEY,
 eligibility_id INT NOT NULL,
 campaign_id INT NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(user_id),
 FOREIGN KEY (eligibility_id) REFERENCES eligibility_checks(eligibility_id),
 FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id)
) ENGINE=InnoDB;

-- Rename only known prototype records; keep dates, status, IDs and registrations.
ALTER TABLE donation_records MODIFY blood_type_collected ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL;
ALTER TABLE contact_replies ADD COLUMN IF NOT EXISTS request_key CHAR(64) NULL;
ALTER TABLE eligibility_checks ADD COLUMN IF NOT EXISTS retry_after DATE NULL;
UPDATE eligibility_checks SET retry_after=DATE_ADD(DATE(COALESCE(reviewed_at,completed_at)),INTERVAL 1 MONTH) WHERE outcome='Not Eligible' AND retry_after IS NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS status_reason VARCHAR(1000) NULL;
CREATE TABLE IF NOT EXISTS account_status_notices (
 notice_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, changed_by INT NULL,
 old_status VARCHAR(20) NULL, new_status VARCHAR(20) NOT NULL, reason VARCHAR(1000) NOT NULL,
 recipient_email VARCHAR(254) NOT NULL, delivery_status VARCHAR(20) NOT NULL DEFAULT 'Pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(user_id), FOREIGN KEY(changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS thread_hash CHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS thread_expires_at DATETIME NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_contact_thread ON contact_messages(thread_hash);
CREATE TABLE IF NOT EXISTS contact_followups (
 followup_id INT AUTO_INCREMENT PRIMARY KEY, message_id INT NOT NULL,
 message_body TEXT NOT NULL, received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(message_id) REFERENCES contact_messages(message_id)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX IF NOT EXISTS uq_contact_reply_request ON contact_replies(request_key);
UPDATE campaigns SET title='Community Blood Donation Drive', location_venue='Mabuhay Community Hall', description='Fictional prototype event: scheduled community blood donation appointments. Not an actual invitation to attend.' WHERE title='Community Donation Drive (DEMO)' AND description LIKE 'DEMONSTRATION ONLY:%';
UPDATE campaigns SET title='University Blood Donation Day', location_venue='San Isidro University Activity Center', description='Fictional prototype event: scheduled community blood donation appointments. Not an actual invitation to attend.' WHERE title='Campus Blood Donation Day (DEMO)' AND description LIKE 'DEMONSTRATION ONLY:%';
UPDATE campaigns SET title='Workplace Blood Donation Campaign', location_venue='Bayanihan Business Center', description='Fictional prototype event: scheduled community blood donation appointments. Not an actual invitation to attend.' WHERE title='Workplace Donation Campaign (DEMO)' AND description LIKE 'DEMONSTRATION ONLY:%';
