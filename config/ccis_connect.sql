/* =========================================================
   CCIS CONNECT DATABASE
   Web-Based Faculty Community and Engagement Platform
   ========================================================= */

CREATE DATABASE IF NOT EXISTS ccis_connect
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ccis_connect;


/* =========================================================
   1. USERS TABLE
   ========================================================= */

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_id VARCHAR(50) NOT NULL UNIQUE,

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    position VARCHAR(150) DEFAULT NULL,

    role ENUM('faculty', 'administrator')
        NOT NULL DEFAULT 'faculty',

    status ENUM('active', 'inactive', 'pending')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_password_reset_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   2. FACULTY GROUPS TABLE
   ========================================================= */

CREATE TABLE faculty_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    group_name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,

    created_by INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_groups_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   3. GROUP MEMBERSHIPS TABLE
   ========================================================= */

CREATE TABLE group_memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    membership_role ENUM('member', 'moderator', 'owner')
        NOT NULL DEFAULT 'member',

    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_group_member (group_id, user_id),

    CONSTRAINT fk_membership_group
        FOREIGN KEY (group_id)
        REFERENCES faculty_groups(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_membership_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   4. ANNOUNCEMENTS TABLE
   ========================================================= */

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,

    created_by INT UNSIGNED NOT NULL,

    status ENUM('draft', 'published', 'archived')
        NOT NULL DEFAULT 'published',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_announcement_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   5. EVENTS TABLE
   ========================================================= */

CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,

    event_date DATETIME NOT NULL,
    location VARCHAR(255) DEFAULT NULL,

    created_by INT UNSIGNED NOT NULL,

    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled')
        NOT NULL DEFAULT 'upcoming',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_event_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   6. EVENT REGISTRATIONS TABLE
   ========================================================= */

CREATE TABLE event_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    registration_status ENUM('registered', 'cancelled', 'attended')
        NOT NULL DEFAULT 'registered',

    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_event_registration (event_id, user_id),

    CONSTRAINT fk_registration_event
        FOREIGN KEY (event_id)
        REFERENCES events(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_registration_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   7. DISCUSSIONS TABLE
   ========================================================= */

CREATE TABLE discussions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,

    created_by INT UNSIGNED NOT NULL,

    status ENUM('active', 'closed', 'archived')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_discussion_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   8. DISCUSSION REPLIES TABLE
   ========================================================= */

CREATE TABLE discussion_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    discussion_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    reply_content TEXT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reply_discussion
        FOREIGN KEY (discussion_id)
        REFERENCES discussions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reply_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   9. POSTS TABLE
   ========================================================= */

CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    content TEXT NOT NULL,

    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_type ENUM('image', 'video', 'file') DEFAULT NULL,
    attachment_size INT UNSIGNED DEFAULT NULL,

    status ENUM('published', 'hidden', 'deleted')
        NOT NULL DEFAULT 'published',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_post_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   10. COMMENTS TABLE
   ========================================================= */

CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    comment_content TEXT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comment_post
        FOREIGN KEY (post_id)
        REFERENCES posts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_comment_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   11. REACTIONS TABLE
   ========================================================= */

CREATE TABLE reactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    reaction_type ENUM('like', 'helpful', 'support')
        NOT NULL DEFAULT 'like',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_post_reaction (post_id, user_id),

    CONSTRAINT fk_reaction_post
        FOREIGN KEY (post_id)
        REFERENCES posts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reaction_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   12. WATER ACCOUNTS TABLE
   ========================================================= */

CREATE TABLE water_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    account_number VARCHAR(50) NOT NULL UNIQUE,
    meter_number VARCHAR(50) DEFAULT NULL,

    office_or_room VARCHAR(150) DEFAULT NULL,

    account_status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_water_account_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   13. WATER BILLS TABLE
   ========================================================= */

CREATE TABLE water_bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    water_account_id INT UNSIGNED NOT NULL,

    bill_number VARCHAR(50) DEFAULT NULL,
    faculty_id INT UNSIGNED DEFAULT NULL,
    billing_period VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,

    previous_reading DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    current_reading DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    consumption DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    rate_per_unit DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    base_charge DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    additional_charge DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    due_date DATE NOT NULL,

    status ENUM(
        'unpaid',
        'partially_paid',
        'cancelled',
        'pending_verification',
        'paid',
        'overdue',
        'rejected'
    ) NOT NULL DEFAULT 'unpaid',

    created_by INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_water_bills_bill_number (bill_number),
    UNIQUE KEY uq_water_bills_faculty_period (faculty_id, billing_period),
    INDEX idx_water_bills_faculty_due_date (faculty_id, due_date),

    CONSTRAINT fk_bill_water_account
        FOREIGN KEY (water_account_id)
        REFERENCES water_accounts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_water_bill_faculty
        FOREIGN KEY (faculty_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_bill_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);


/* =========================================================
   14. PAYMENT METHODS TABLE
   ========================================================= */

CREATE TABLE payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    method_name VARCHAR(100) NOT NULL UNIQUE,

    description VARCHAR(255) DEFAULT NULL,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);


/* =========================================================
   15. PAYMENTS TABLE
   ========================================================= */

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    bill_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    payment_method_id INT UNSIGNED NOT NULL,

    amount_paid DECIMAL(10,2) NOT NULL,

    transaction_reference VARCHAR(150)
        DEFAULT NULL UNIQUE,

    proof_file VARCHAR(255)
        DEFAULT NULL,

    payment_status ENUM(
        'pending',
        'verified',
        'rejected',
        'processing',
        'paid',
        'failed',
        'cancelled',
        'refunded'
    ) NOT NULL DEFAULT 'pending',

    remarks TEXT DEFAULT NULL,

    verified_by INT UNSIGNED DEFAULT NULL,

    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    verified_at DATETIME DEFAULT NULL,

    payment_reference VARCHAR(64) DEFAULT NULL UNIQUE,
    faculty_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'PHP',
    gateway VARCHAR(30) NOT NULL DEFAULT 'manual',
    gateway_checkout_id VARCHAR(100) DEFAULT NULL,
    gateway_checkout_url TEXT DEFAULT NULL,
    gateway_payment_id VARCHAR(100) DEFAULT NULL,
    gateway_transaction_id VARCHAR(100) DEFAULT NULL,
    payment_method VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled', 'refunded')
        NOT NULL DEFAULT 'pending',
    gateway_status VARCHAR(50) DEFAULT NULL,
    verification_status ENUM('pending', 'verified', 'failed', 'manual_review')
        NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_payments_payment_reference (payment_reference),
    UNIQUE KEY uq_payments_gateway_checkout (gateway_checkout_id),
    UNIQUE KEY uq_payments_gateway_payment (gateway_payment_id),
    UNIQUE KEY uq_payments_gateway_transaction (gateway_transaction_id),
    INDEX idx_payments_bill_status (bill_id, status),
    INDEX idx_payments_faculty_created (faculty_id, created_at),

    CONSTRAINT fk_payment_bill
        FOREIGN KEY (bill_id)
        REFERENCES water_bills(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_payment_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_payment_faculty
        FOREIGN KEY (faculty_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_payment_method
        FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_payment_verified_by
        FOREIGN KEY (verified_by)
        REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE payment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_event_id VARCHAR(150) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    gateway VARCHAR(30) NOT NULL,
    processing_status ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    payload LONGTEXT NOT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    INDEX idx_payment_events_payment (payment_id, received_at),
    CONSTRAINT fk_payment_event_payment
        FOREIGN KEY (payment_id)
        REFERENCES payments(id)
        ON DELETE SET NULL
);


/* =========================================================
   16. RECEIPTS TABLE
   ========================================================= */

CREATE TABLE receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    payment_id INT UNSIGNED NOT NULL UNIQUE,

    receipt_number VARCHAR(100) NOT NULL UNIQUE,

    receipt_file VARCHAR(255) DEFAULT NULL,

    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    faculty_id INT UNSIGNED DEFAULT NULL,
    bill_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gateway VARCHAR(30) DEFAULT NULL,
    gateway_transaction_id VARCHAR(100) DEFAULT NULL,

    INDEX idx_receipts_faculty (faculty_id),

    CONSTRAINT fk_receipt_payment
        FOREIGN KEY (payment_id)
        REFERENCES payments(id)
        ON DELETE CASCADE
);


/* =========================================================
   17. NOTIFICATIONS TABLE
   ========================================================= */

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,

    notification_type ENUM(
        'announcement',
        'event',
        'payment',
        'system'
    ) NOT NULL DEFAULT 'system',

    is_read BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notifications_user_created (user_id, created_at),

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   18. MESSAGES TABLE
   ========================================================= */

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,

    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,

    is_read BOOLEAN NOT NULL DEFAULT FALSE,

    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_message_sender
        FOREIGN KEY (sender_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_message_receiver
        FOREIGN KEY (receiver_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


/* =========================================================
   19. AUDIT LOGS TABLE
   ========================================================= */

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED DEFAULT NULL,

    action VARCHAR(255) NOT NULL,

    description TEXT DEFAULT NULL,

    ip_address VARCHAR(45) DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
);


/* =========================================================
   SAMPLE DATA
   ========================================================= */


/* ---------------------------------------------------------
   A. SAMPLE USERS

   The password hash below is for:
   Ccis!Orbit2026#Pine

   Change these accounts and passwords in a real deployment.
   --------------------------------------------------------- */

INSERT INTO users (
    employee_id,
    first_name,
    last_name,
    email,
    password,
    position,
    role,
    status
) VALUES
(
    'ADMIN-001',
    'System',
    'Administrator',
    'admin@ccis.local',
    '$2y$10$ukcJsIeZTrRtCVCWFUjdkOpiTbJc8rvMRvQT5WsO7fca7O.4f5Vne',
    'System Administrator',
    'administrator',
    'active'
),
(
    'FAC-001',
    'Juan',
    'Dela Cruz',
    'juan.delacruz@ccis.local',
    '$2y$10$ukcJsIeZTrRtCVCWFUjdkOpiTbJc8rvMRvQT5WsO7fca7O.4f5Vne',
    'Faculty Member',
    'faculty',
    'active'
),
(
    'FAC-002',
    'Maria',
    'Santos',
    'maria.santos@ccis.local',
    '$2y$10$ukcJsIeZTrRtCVCWFUjdkOpiTbJc8rvMRvQT5WsO7fca7O.4f5Vne',
    'Associate Professor',
    'faculty',
    'active'
),
(
    'FAC-003',
    'Pedro',
    'Reyes',
    'pedro.reyes@ccis.local',
    '$2y$10$ukcJsIeZTrRtCVCWFUjdkOpiTbJc8rvMRvQT5WsO7fca7O.4f5Vne',
    'Assistant Professor',
    'faculty',
    'active'
);


/* ---------------------------------------------------------
   B. SAMPLE FACULTY GROUPS
   --------------------------------------------------------- */

INSERT INTO faculty_groups (
    group_name,
    description,
    created_by
) VALUES
(
    'CCIS Faculty General Group',
    'General group for announcements, discussions, and faculty coordination.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001')
),
(
    'Information Technology Faculty',
    'Group for faculty members from the Information Technology department.',
    (SELECT id FROM users WHERE employee_id = 'FAC-002')
),
(
    'CCIS Research and Development',
    'Group for research collaboration and project discussions.',
    (SELECT id FROM users WHERE employee_id = 'FAC-003')
);


/* ---------------------------------------------------------
   C. SAMPLE GROUP MEMBERSHIPS
   --------------------------------------------------------- */

INSERT INTO group_memberships (
    group_id,
    user_id,
    membership_role
) VALUES
(
    (SELECT id FROM faculty_groups
     WHERE group_name = 'CCIS Faculty General Group'),
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'member'
),
(
    (SELECT id FROM faculty_groups
     WHERE group_name = 'CCIS Faculty General Group'),
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'member'
),
(
    (SELECT id FROM faculty_groups
     WHERE group_name = 'CCIS Faculty General Group'),
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'member'
),
(
    (SELECT id FROM faculty_groups
     WHERE group_name = 'Information Technology Faculty'),
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'owner'
),
(
    (SELECT id FROM faculty_groups
     WHERE group_name = 'CCIS Research and Development'),
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'owner'
);


/* ---------------------------------------------------------
   D. SAMPLE ANNOUNCEMENTS
   --------------------------------------------------------- */

INSERT INTO announcements (
    title,
    content,
    created_by,
    status
) VALUES
(
    'Welcome to CCIS Connect',
    'Welcome to the CCIS Connect faculty community and engagement platform.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'published'
),
(
    'Monthly Faculty Meeting',
    'The monthly CCIS faculty meeting will be held in the conference room.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'published'
),
(
    'Submission of Faculty Reports',
    'All faculty members are reminded to submit their monthly reports before the deadline.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'published'
);


/* ---------------------------------------------------------
   E. SAMPLE EVENTS
   --------------------------------------------------------- */

INSERT INTO events (
    title,
    description,
    event_date,
    location,
    created_by,
    status
) VALUES
(
    'CCIS Faculty General Meeting',
    'Monthly meeting for all CCIS faculty members.',
    '2026-09-05 09:00:00',
    'CCIS Conference Room',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'upcoming'
),
(
    'Faculty Development Workshop',
    'Workshop focused on improving teaching and technology skills.',
    '2026-09-15 13:00:00',
    'Computer Laboratory 1',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'upcoming'
),
(
    'Research Collaboration Forum',
    'Discussion of current and proposed CCIS research projects.',
    '2026-09-25 10:00:00',
    'Research Room',
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'upcoming'
);


/* ---------------------------------------------------------
   F. SAMPLE EVENT REGISTRATIONS
   --------------------------------------------------------- */

INSERT INTO event_registrations (
    event_id,
    user_id,
    registration_status
) VALUES
(
    (SELECT id FROM events
     WHERE title = 'CCIS Faculty General Meeting'),
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'registered'
),
(
    (SELECT id FROM events
     WHERE title = 'CCIS Faculty General Meeting'),
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'registered'
),
(
    (SELECT id FROM events
     WHERE title = 'Faculty Development Workshop'),
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'registered'
);


/* ---------------------------------------------------------
   G. SAMPLE DISCUSSIONS
   --------------------------------------------------------- */

INSERT INTO discussions (
    title,
    content,
    created_by,
    status
) VALUES
(
    'Suggestions for Faculty Activities',
    'What activities would you like to include in the next faculty program?',
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'active'
),
(
    'Research Project Collaboration',
    'Faculty members interested in research collaboration may reply to this discussion.',
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'active'
);


/* ---------------------------------------------------------
   H. SAMPLE DISCUSSION REPLIES
   --------------------------------------------------------- */

INSERT INTO discussion_replies (
    discussion_id,
    user_id,
    reply_content
) VALUES
(
    (SELECT id FROM discussions
     WHERE title = 'Suggestions for Faculty Activities'),
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'I suggest organizing a faculty technology workshop.'
),
(
    (SELECT id FROM discussions
     WHERE title = 'Research Project Collaboration'),
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'I am interested in joining a research project.'
);


/* ---------------------------------------------------------
   I. SAMPLE POSTS
   --------------------------------------------------------- */

INSERT INTO posts (
    user_id,
    content,
    status
) VALUES
(
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'Good morning, everyone. Welcome to CCIS Connect.',
    'published'
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'The faculty development workshop sounds very useful.',
    'published'
);


/* ---------------------------------------------------------
   J. SAMPLE COMMENTS
   --------------------------------------------------------- */

INSERT INTO comments (
    post_id,
    user_id,
    comment_content
) VALUES
(
    (
        SELECT id FROM posts
        WHERE content = 'Good morning, everyone. Welcome to CCIS Connect.'
    ),
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'Welcome! I look forward to using the platform.'
);


/* ---------------------------------------------------------
   K. PAYMENT METHODS
   --------------------------------------------------------- */

INSERT INTO payment_methods (
    method_name,
    description,
    is_active
) VALUES
(
    'Cash',
    'Payment made directly through the authorized cashier.',
    TRUE
),
(
    'Bank Transfer',
    'Payment made through an approved bank transfer.',
    TRUE
),
(
    'E-Wallet',
    'Payment made through an approved digital wallet.',
    TRUE
),
(
    'Online Payment',
    'Payment processed through an online payment gateway.',
    TRUE
);


/* ---------------------------------------------------------
   L. WATER ACCOUNTS
   --------------------------------------------------------- */

INSERT INTO water_accounts (
    user_id,
    account_number,
    meter_number,
    office_or_room,
    account_status
) VALUES
(
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'WTR-FAC-001',
    'MTR-001',
    'CCIS Faculty Room 1',
    'active'
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'WTR-FAC-002',
    'MTR-002',
    'CCIS Faculty Room 2',
    'active'
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'WTR-FAC-003',
    'MTR-003',
    'CCIS Faculty Room 3',
    'active'
);


/* ---------------------------------------------------------
   M. WATER BILLS
   --------------------------------------------------------- */

INSERT INTO water_bills (
    water_account_id,
    billing_period,
    previous_reading,
    current_reading,
    consumption,
    rate_per_unit,
    base_charge,
    additional_charge,
    total_amount,
    due_date,
    status,
    created_by
) VALUES
(
    (
        SELECT id FROM water_accounts
        WHERE account_number = 'WTR-FAC-001'
    ),
    'August 2026',
    100.00,
    125.00,
    25.00,
    30.00,
    500.00,
    0.00,
    1250.00,
    '2026-08-30',
    'unpaid',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001')
),
(
    (
        SELECT id FROM water_accounts
        WHERE account_number = 'WTR-FAC-002'
    ),
    'August 2026',
    210.00,
    235.00,
    25.00,
    30.00,
    500.00,
    0.00,
    1250.00,
    '2026-08-30',
    'pending_verification',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001')
),
(
    (
        SELECT id FROM water_accounts
        WHERE account_number = 'WTR-FAC-003'
    ),
    'August 2026',
    300.00,
    320.00,
    20.00,
    30.00,
    500.00,
    0.00,
    1100.00,
    '2026-08-30',
    'paid',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001')
),
(
    (
        SELECT id FROM water_accounts
        WHERE account_number = 'WTR-FAC-001'
    ),
    'July 2026',
    75.00,
    100.00,
    25.00,
    30.00,
    500.00,
    0.00,
    1250.00,
    '2026-07-30',
    'paid',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001')
);


/* ---------------------------------------------------------
   N. SAMPLE PAYMENTS
   --------------------------------------------------------- */

INSERT INTO payments (
    bill_id,
    user_id,
    payment_method_id,
    amount_paid,
    transaction_reference,
    proof_file,
    payment_status,
    remarks,
    verified_by,
    payment_date,
    verified_at
) VALUES
(
    (
        SELECT wb.id
        FROM water_bills wb
        INNER JOIN water_accounts wa
            ON wa.id = wb.water_account_id
        WHERE wa.account_number = 'WTR-FAC-002'
        AND wb.billing_period = 'August 2026'
    ),
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    (
        SELECT id FROM payment_methods
        WHERE method_name = 'Bank Transfer'
    ),
    1250.00,
    'BANK-202608-0001',
    'sample_receipt_fac002.jpg',
    'pending',
    'Payment proof submitted for verification.',
    NULL,
    '2026-08-20 10:30:00',
    NULL
),
(
    (
        SELECT wb.id
        FROM water_bills wb
        INNER JOIN water_accounts wa
            ON wa.id = wb.water_account_id
        WHERE wa.account_number = 'WTR-FAC-003'
        AND wb.billing_period = 'August 2026'
    ),
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    (
        SELECT id FROM payment_methods
        WHERE method_name = 'E-Wallet'
    ),
    1100.00,
    'EWALLET-202608-0001',
    'sample_receipt_fac003.jpg',
    'verified',
    'Payment verified by the finance officer.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    '2026-08-18 14:00:00',
    '2026-08-18 15:00:00'
),
(
    (
        SELECT wb.id
        FROM water_bills wb
        INNER JOIN water_accounts wa
            ON wa.id = wb.water_account_id
        WHERE wa.account_number = 'WTR-FAC-001'
        AND wb.billing_period = 'July 2026'
    ),
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    (
        SELECT id FROM payment_methods
        WHERE method_name = 'Cash'
    ),
    1250.00,
    'CASH-202607-0001',
    NULL,
    'verified',
    'Cash payment recorded by the administrator.',
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    '2026-07-25 09:00:00',
    '2026-07-25 10:00:00'
);


/* ---------------------------------------------------------
   O. SAMPLE RECEIPTS
   --------------------------------------------------------- */

INSERT INTO receipts (
    payment_id,
    receipt_number,
    receipt_file,
    issued_at
) VALUES
(
    (
        SELECT id FROM payments
        WHERE transaction_reference = 'EWALLET-202608-0001'
    ),
    'CCIS-REC-2026-0001',
    'receipt_ccis_2026_0001.pdf',
    '2026-08-18 15:05:00'
),
(
    (
        SELECT id FROM payments
        WHERE transaction_reference = 'CASH-202607-0001'
    ),
    'CCIS-REC-2026-0002',
    'receipt_ccis_2026_0002.pdf',
    '2026-07-25 10:05:00'
);


/* ---------------------------------------------------------
   P. SAMPLE NOTIFICATIONS
   --------------------------------------------------------- */

INSERT INTO notifications (
    user_id,
    title,
    message,
    notification_type,
    is_read
) VALUES
(
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'Water Bill Available',
    'Your August 2026 water bill amounting to ₱1,250.00 is now available.',
    'payment',
    FALSE
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-002'),
    'Payment Under Review',
    'Your August 2026 water bill payment is currently pending verification.',
    'payment',
    FALSE
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-003'),
    'Payment Verified',
    'Your August 2026 water bill payment has been verified.',
    'payment',
    TRUE
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'Upcoming Faculty Meeting',
    'The CCIS Faculty General Meeting will be held on September 5, 2026.',
    'event',
    FALSE
);


/* ---------------------------------------------------------
   Q. SAMPLE AUDIT LOGS
   --------------------------------------------------------- */

INSERT INTO audit_logs (
    user_id,
    action,
    ip_address
) VALUES
(
    (SELECT id FROM users WHERE employee_id = 'ADMIN-001'),
    'Initial system database setup',
    '127.0.0.1'
),
(
    (SELECT id FROM users WHERE employee_id = 'FAC-001'),
    'Sample faculty account created',
    '127.0.0.1'
);


/* =========================================================
   USEFUL VIEWS
   ========================================================= */


/* View showing faculty water bills */

CREATE OR REPLACE VIEW faculty_water_bill_summary AS
SELECT
    wb.id AS bill_id,
    u.employee_id,
    CONCAT(u.first_name, ' ', u.last_name) AS faculty_name,
    u.email,
    wa.account_number,
    wa.meter_number,
    wa.office_or_room,
    wb.billing_period,
    wb.previous_reading,
    wb.current_reading,
    wb.consumption,
    wb.rate_per_unit,
    wb.base_charge,
    wb.additional_charge,
    wb.total_amount,
    wb.due_date,
    wb.status,
    wb.created_at
FROM water_bills wb
INNER JOIN water_accounts wa
    ON wa.id = wb.water_account_id
INNER JOIN users u
    ON u.id = wa.user_id;


/* View showing payment records */

CREATE OR REPLACE VIEW payment_summary AS
SELECT
    p.id AS payment_id,
    p.transaction_reference,
    u.employee_id,
    CONCAT(u.first_name, ' ', u.last_name) AS faculty_name,
    wb.billing_period,
    wb.total_amount AS bill_amount,
    p.amount_paid,
    pm.method_name AS payment_method,
    p.payment_status,
    p.payment_date,
    p.verified_at,
    CONCAT(v.first_name, ' ', v.last_name) AS verified_by
FROM payments p
INNER JOIN users u
    ON u.id = p.user_id
INNER JOIN water_bills wb
    ON wb.id = p.bill_id
INNER JOIN payment_methods pm
    ON pm.id = p.payment_method_id
LEFT JOIN users v
    ON v.id = p.verified_by;
