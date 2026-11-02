-- ============================================================================
-- ExamSphere — College Edition
-- Clean database schema
-- Target: MySQL 8+ / MariaDB
--
-- Derived from:
--   db_setup.sql
--   auth.sql
--   PHP query and deletion logic
--
-- This script creates the fresh-install schema only. It intentionally does not
-- define attendance because the repository references it as an optional table
-- but does not provide its complete CREATE TABLE definition.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `exam_room_allocation_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `exam_room_allocation_system`;

-- ============================================================================
-- Academic master data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `DEPARTMENT` (
    `DEPT_ID`   INT NOT NULL,
    `DEPT_NAME` VARCHAR(100) NOT NULL,

    PRIMARY KEY (`DEPT_ID`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `SUBJECT` (
    `SUBJECT_ID`       VARCHAR(50) NOT NULL,
    `SUBJECT_NAME`     VARCHAR(100) NOT NULL,
    `OFFERED_SEMESTER` INT NOT NULL,
    `DEPT_ID`          INT NOT NULL,

    PRIMARY KEY (`SUBJECT_ID`),
    KEY `idx_subject_department` (`DEPT_ID`),
    CONSTRAINT `fk_subject_department`
        FOREIGN KEY (`DEPT_ID`)
        REFERENCES `DEPARTMENT` (`DEPT_ID`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `STUDENTS` (
    `STUDENT_ID`       INT NOT NULL AUTO_INCREMENT,
    `FNAME`            VARCHAR(50) NOT NULL,
    `MNAME`            VARCHAR(50) NULL,
    `LNAME`            VARCHAR(50) NOT NULL,
    `CURRENT_SEMESTER` INT NOT NULL,
    `DEPT_ID`          INT NOT NULL,

    PRIMARY KEY (`STUDENT_ID`),
    KEY `idx_students_department` (`DEPT_ID`),
    CONSTRAINT `fk_students_department`
        FOREIGN KEY (`DEPT_ID`)
        REFERENCES `DEPARTMENT` (`DEPT_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Examination scheduling
-- ============================================================================

CREATE TABLE IF NOT EXISTS `EXAMINATION` (
    `EXAM_ID`     VARCHAR(191) NOT NULL,
    `SUBJECT_ID`  VARCHAR(50) NOT NULL,
    `EXAM_DATE`   DATE NOT NULL,
    `START_TIME`  TIME NOT NULL,
    `END_TIME`    TIME NOT NULL,
    `published`   TINYINT(1) NOT NULL DEFAULT 1,

    PRIMARY KEY (`EXAM_ID`),
    KEY `idx_examination_subject` (`SUBJECT_ID`),
    KEY `idx_examination_date` (`EXAM_DATE`, `START_TIME`),
    CONSTRAINT `fk_examination_subject`
        FOREIGN KEY (`SUBJECT_ID`)
        REFERENCES `SUBJECT` (`SUBJECT_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Rooms and invigilators
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ROOMS` (
    `ROOM_ID`  VARCHAR(50) NOT NULL,
    `BUILDING` VARCHAR(50) NULL,
    `FLOOR`    VARCHAR(10) NULL,
    `CAPACITY` INT NOT NULL,
    `STATUS`   VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',

    PRIMARY KEY (`ROOM_ID`),
    KEY `idx_rooms_status` (`STATUS`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `INVIGILATOR` (
    `INVIGILATOR_ID`   INT NOT NULL,
    `INVIGILATOR_NAME` VARCHAR(100) NOT NULL,
    `PHONE`            VARCHAR(20) NULL,
    `EMAIL`            VARCHAR(100) NULL,

    PRIMARY KEY (`INVIGILATOR_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Student examination registration
-- ============================================================================

CREATE TABLE IF NOT EXISTS `EXAM_REGISTRATION` (
    `REGISTR_ID`   INT NOT NULL AUTO_INCREMENT,
    `STUDENT_ID`   INT NOT NULL,
    `EXAM_ID`      VARCHAR(191) NOT NULL,
    `REGISTR_DATE` DATE NOT NULL,

    PRIMARY KEY (`REGISTR_ID`),
    UNIQUE KEY `uq_registration_student_exam` (`STUDENT_ID`, `EXAM_ID`),
    KEY `idx_registration_exam` (`EXAM_ID`),
    CONSTRAINT `fk_registration_student`
        FOREIGN KEY (`STUDENT_ID`)
        REFERENCES `STUDENTS` (`STUDENT_ID`),
    CONSTRAINT `fk_registration_exam`
        FOREIGN KEY (`EXAM_ID`)
        REFERENCES `EXAMINATION` (`EXAM_ID`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- Seat allocation
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ROOM_ALLOCATION` (
    `ALLOCATION_ID` INT NOT NULL AUTO_INCREMENT,
    `REGISTR_ID`    INT NOT NULL,
    `ROOM_ID`       VARCHAR(50) NOT NULL,
    `SEAT_NO`       VARCHAR(10) NOT NULL,

    PRIMARY KEY (`ALLOCATION_ID`),
    UNIQUE KEY `uq_allocation_registration` (`REGISTR_ID`),
    UNIQUE KEY `uq_allocation_room_seat` (`ROOM_ID`, `SEAT_NO`),
    KEY `idx_allocation_room` (`ROOM_ID`),
    CONSTRAINT `fk_allocation_registration`
        FOREIGN KEY (`REGISTR_ID`)
        REFERENCES `EXAM_REGISTRATION` (`REGISTR_ID`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_allocation_room`
        FOREIGN KEY (`ROOM_ID`)
        REFERENCES `ROOMS` (`ROOM_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Invigilator duties
-- ============================================================================

CREATE TABLE IF NOT EXISTS `INVIGILATOR_DUTY` (
    `DUTY_ID`        VARCHAR(300) NOT NULL,
    `EXAM_ID`        VARCHAR(191) NOT NULL,
    `INVIGILATOR_ID` INT NOT NULL,
    `ROOM_ID`        VARCHAR(50) NOT NULL,

    PRIMARY KEY (`DUTY_ID`),
    UNIQUE KEY `uq_duty_exam_invigilator_room`
        (`EXAM_ID`, `INVIGILATOR_ID`, `ROOM_ID`),
    KEY `idx_duty_invigilator` (`INVIGILATOR_ID`),
    KEY `idx_duty_room` (`ROOM_ID`),
    CONSTRAINT `fk_duty_exam`
        FOREIGN KEY (`EXAM_ID`)
        REFERENCES `EXAMINATION` (`EXAM_ID`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_duty_invigilator`
        FOREIGN KEY (`INVIGILATOR_ID`)
        REFERENCES `INVIGILATOR` (`INVIGILATOR_ID`),
    CONSTRAINT `fk_duty_room`
        FOREIGN KEY (`ROOM_ID`)
        REFERENCES `ROOMS` (`ROOM_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Application user accounts
--
-- student_id and invigilator_id are nullable unique links. An administrator
-- account normally has both profile links NULL. The role determines which
-- portal the account can access.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `user_id`        INT NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(60) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `full_name`      VARCHAR(120) NOT NULL,
    `email`          VARCHAR(160) NULL,
    `role`           VARCHAR(20) NOT NULL,
    `student_id`     INT NULL,
    `invigilator_id` INT NULL,

    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_student` (`student_id`),
    UNIQUE KEY `uq_users_invigilator` (`invigilator_id`),
    CONSTRAINT `ck_users_role`
        CHECK (`role` IN ('admin', 'student', 'invigilator')),
    CONSTRAINT `fk_users_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `STUDENTS` (`STUDENT_ID`),
    CONSTRAINT `fk_users_invigilator`
        FOREIGN KEY (`invigilator_id`)
        REFERENCES `INVIGILATOR` (`INVIGILATOR_ID`)
) ENGINE=InnoDB;

-- ============================================================================
-- Separate administrator authentication table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ADMIN_USERS` (
    `ADMIN_ID`     INT NOT NULL AUTO_INCREMENT,
    `FULL_NAME`    VARCHAR(100) NOT NULL,
    `USERNAME`     VARCHAR(50) NOT NULL,
    `PASSWORD_HASH` VARCHAR(255) NOT NULL,
    `CREATED_AT`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`ADMIN_ID`),
    UNIQUE KEY `uq_admin_users_username` (`USERNAME`)
) ENGINE=InnoDB;

-- ============================================================================
-- End of ExamSphere schema
-- ============================================================================
--
-- Optional attendance note:
-- Existing deployments may contain an `attendance` table linked to
-- ROOM_ALLOCATION.ALLOCATION_ID. Its complete definition is not supplied by
-- this repository, so it is intentionally not fabricated here.
-- ============================================================================
