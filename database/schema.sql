-- ============================================================================
-- StudentHub - Digital Campus Portal
-- PRACTICAL 8: MySQL Schema Design, ER Model, PDO Connectivity, and Prepared Statements
-- ============================================================================
-- Description:
-- Complete relational database schema for StudentHub portal.
-- Includes tables for students, events, event_registrations, and contact_inquiries,
-- with proper constraints (PK, FK, UNIQUE, CHECK), indexes, and initial seed data.
-- ============================================================================

-- 1. DATABASE CREATION
CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- ----------------------------------------------------------------------------
-- 2. TABLE: students
-- Stores registered student profile credentials and academic records.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `event_registrations`;
DROP TABLE IF EXISTS `contact_inquiries`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
    `student_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `registration_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formatted ID e.g. STU20261012001',
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique portal username e.g. rahul_patel',
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `mobile` VARCHAR(15) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password via password_hash()',
    `course` VARCHAR(50) NOT NULL COMMENT 'B.Tech IT, B.Tech CSE, BCA, etc.',
    `academic_year` TINYINT UNSIGNED NOT NULL COMMENT '1, 2, 3, or 4',
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `terms_accepted` ENUM('Accepted', 'Pending') NOT NULL DEFAULT 'Accepted',
    `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student' COMMENT 'Role-based access control (RBAC)',
    `remember_token` VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash of persistent remember-me token',
    `token_expiry` DATETIME DEFAULT NULL COMMENT 'Expiration datetime for remember-me token',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_students_username` (`username`),
    INDEX `idx_students_email` (`email`),
    INDEX `idx_students_role` (`role`),
    INDEX `idx_students_course_year` (`course`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. TABLE: events
-- Stores campus academic, cultural, sports, and technical events.
-- ----------------------------------------------------------------------------
CREATE TABLE `events` (
    `event_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL COMMENT 'Technology, Competition, Workshop, Sports, Cultural',
    `event_date` DATE NOT NULL,
    `event_time` VARCHAR(30) NOT NULL,
    `location` VARCHAR(120) NOT NULL,
    `description` TEXT NOT NULL,
    `organizer` VARCHAR(120) NOT NULL,
    `total_seats` INT UNSIGNED NOT NULL,
    `available_seats` INT UNSIGNED NOT NULL,
    `status` ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Upcoming',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_events_date` (`event_date`),
    INDEX `idx_events_category` (`category`),
    INDEX `idx_events_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. TABLE: event_registrations (Associative Table)
-- Resolves Many-to-Many (M:N) relationship between Students and Events.
-- Enforces referential integrity with Foreign Keys and CASCADE rules.
-- ----------------------------------------------------------------------------
CREATE TABLE `event_registrations` (
    `registration_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    `registration_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique ticket/token e.g. REG-2026-001',
    `registration_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `attendance_status` ENUM('Confirmed', 'Attended', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    `remarks` VARCHAR(255) DEFAULT NULL,

    -- Foreign Key constraints
    CONSTRAINT `fk_registrations_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_registrations_event`
        FOREIGN KEY (`event_id`)
        REFERENCES `events` (`event_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Prevent a student from registering for the same event multiple times
    UNIQUE KEY `unique_student_event_registration` (`student_id`, `event_id`),
    INDEX `idx_registrations_date` (`registration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5. TABLE: contact_inquiries
-- Stores campus queries and feedback submitted through the contact form.
-- ----------------------------------------------------------------------------
CREATE TABLE `contact_inquiries` (
    `inquiry_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g. MSG20261012001',
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `mobile` VARCHAR(15) DEFAULT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('New', 'In Review', 'Resolved') NOT NULL DEFAULT 'New',
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_inquiries_email` (`email`),
    INDEX `idx_inquiries_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 6. INITIAL SEED DATA
-- Populate realistic initial records corresponding to Practical 5, 6, and 7.
-- ============================================================================

-- Seed Students and Administrator (Practical 10 RBAC)
-- Note: All sample accounts have password: "password" (Bcrypt hashed)
INSERT INTO `students`
    (`registration_no`, `username`, `full_name`, `email`, `mobile`, `password_hash`, `course`, `academic_year`, `gender`, `terms_accepted`, `role`, `created_at`)
VALUES
    ('ADM20261001001', 'admin', 'Campus Administrator', 'admin@studenthub.com', '9999999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administration', 4, 'Other', 'Accepted', 'admin', '2026-10-01 09:00:00'),
    ('STU20261012001', 'rahul_patel', 'Rahul Patel', 'rahul@example.com', '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B.Tech IT', 3, 'Male', 'Accepted', 'student', '2026-10-12 10:30:00'),
    ('STU20261012002', 'priya_shah', 'Priya Shah', 'priya@example.com', '9876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B.Tech CSE', 2, 'Female', 'Accepted', 'student', '2026-10-12 10:35:00'),
    ('STU20261012003', 'aarav_m', 'Aarav Mehta', 'aarav@example.com', '9876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BCA', 1, 'Male', 'Accepted', 'student', '2026-10-12 10:40:00'),
    ('STU20261013004', 'ananya_j', 'Ananya Joshi', 'ananya.j@example.com', '9876543213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B.Tech CE', 4, 'Female', 'Accepted', 'student', '2026-10-13 09:15:00');

-- Seed Events (from events.json)
INSERT INTO `events`
    (`event_id`, `title`, `category`, `event_date`, `event_time`, `location`, `description`, `organizer`, `total_seats`, `available_seats`, `status`)
VALUES
    (1, 'Tech Innovation Summit 2026', 'Technology', '2026-10-10', '10:00 AM', 'Main Auditorium', 'Explore emerging technologies, AI innovations and modern software development.', 'Computer Engineering Department', 120, 118, 'Completed'),
    (2, 'Campus Coding Challenge', 'Competition', '2026-10-15', '02:00 PM', 'Computer Lab 1', 'Participate in an exciting programming competition and solve real-world coding problems.', 'Coding Club', 80, 78, 'Upcoming'),
    (3, 'Entrepreneurship Workshop', 'Workshop', '2026-10-20', '11:00 AM', 'Seminar Hall', 'Learn how to transform innovative ideas into practical startup opportunities.', 'Innovation Cell', 100, 99, 'Upcoming'),
    (4, 'Sports Fest 2026', 'Sports', '2026-10-25', '09:00 AM', 'University Ground', 'Take part in various indoor and outdoor sports activities.', 'Sports Committee', 250, 250, 'Upcoming'),
    (5, 'Web Development Bootcamp', 'Workshop', '2026-11-02', '10:30 AM', 'IT Lab', 'A practical bootcamp covering HTML, CSS, JavaScript and modern web development.', 'IT Department', 60, 59, 'Upcoming'),
    (6, 'Cultural Night', 'Cultural', '2026-11-08', '06:00 PM', 'Open Air Theatre', 'Celebrate creativity, culture and student talent through performances and activities.', 'Student Council', 300, 298, 'Upcoming');

-- Seed Event Registrations
INSERT INTO `event_registrations`
    (`student_id`, `event_id`, `registration_code`, `registration_date`, `attendance_status`, `remarks`)
VALUES
    (1, 1, 'REG-2026-001', '2026-10-08 14:20:00', 'Attended', 'Participant in AI Track'),
    (2, 1, 'REG-2026-002', '2026-10-08 15:45:00', 'Attended', 'Registered for Keynote'),
    (1, 2, 'REG-2026-003', '2026-10-11 11:10:00', 'Confirmed', 'Individual category'),
    (3, 2, 'REG-2026-004', '2026-10-11 16:30:00', 'Confirmed', 'Team lead'),
    (2, 3, 'REG-2026-005', '2026-10-12 09:00:00', 'Confirmed', 'Startup pitch attendee'),
    (4, 5, 'REG-2026-006', '2026-10-12 18:25:00', 'Confirmed', 'Hands-on lab attendee'),
    (1, 6, 'REG-2026-007', '2026-10-13 08:30:00', 'Confirmed', 'Audience pass'),
    (4, 6, 'REG-2026-008', '2026-10-13 08:45:00', 'Confirmed', 'Music performance participant');

-- Seed Contact Inquiries (from contacts.csv)
INSERT INTO `contact_inquiries`
    (`reference_no`, `full_name`, `email`, `mobile`, `subject`, `message`, `status`, `submitted_at`)
VALUES
    ('MSG20261012001', 'Ananya Sharma', 'ananya@example.com', '9898989898', 'Library Access Inquiry', 'How can I get remote access to the digital library database?', 'Resolved', '2026-10-12 11:15:00'),
    ('MSG20261012002', 'Karan Joshi', 'karan@example.com', '9797979797', 'Event Schedule', 'Could you please update the schedule for the upcoming hackathon?', 'In Review', '2026-10-12 14:20:00');
