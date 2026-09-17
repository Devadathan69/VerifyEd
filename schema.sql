CREATE DATABASE IF NOT EXISTS verifyed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verifyed;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'verifier') NOT NULL DEFAULT 'student',
    profile_headline VARCHAR(160) NULL,
    biography TEXT NULL,
    location VARCHAR(100) NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    provider VARCHAR(160) NOT NULL,
    completion_date DATE NOT NULL,
    credential_id VARCHAR(120) NULL,
    skills VARCHAR(500) NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(80) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    status ENUM('pending', 'verified', 'needs_revision') NOT NULL DEFAULT 'pending',
    verifier_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    share_token CHAR(32) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificate_student FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_certificate_verifier FOREIGN KEY (verifier_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_certificate_status_created (status, created_at),
    INDEX idx_certificate_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE certificate_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT UNSIGNED NOT NULL,
    verifier_id INT UNSIGNED NOT NULL,
    outcome ENUM('verified', 'needs_revision') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_certificate FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_verifier FOREIGN KEY (verifier_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_review_certificate (certificate_id, created_at)
) ENGINE=InnoDB;

-- After registering an account, promote a trusted reviewer manually:
-- UPDATE users SET role = 'verifier' WHERE email = 'reviewer@example.com';
