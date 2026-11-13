CREATE TABLE IF NOT EXISTS Email_verification_codes (
    Id_verifications BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Id_users INT NULL,
    purpose ENUM(
        'signup',
        'email_change',
        'email_change_confirm',
        'password_change',
        'password_reset'
    ) NOT NULL,
    email VARCHAR(100) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    pending_name VARCHAR(255) NULL,
    pending_first_name VARCHAR(255) NULL,
    pending_password VARCHAR(255) NULL,
    pending_email VARCHAR(100) NULL,
    expires_at DATETIME NOT NULL,
    last_sent_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (Id_users, purpose, email),

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

ALTER TABLE Email_verification_codes
    MODIFY purpose ENUM(
        'signup',
        'email_change',
        'email_change_confirm',
        'password_change',
        'password_reset'
    ) NOT NULL;
