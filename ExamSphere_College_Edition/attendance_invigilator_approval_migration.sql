-- Run once against an existing ExamSphere database.

-- Preserve both known foreign keys while changing the invigilator ID attribute.
SET @duty_fk = NULL;
SET @duty_delete_rule = NULL;
SET @duty_update_rule = NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
INTO @duty_fk, @duty_delete_rule, @duty_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'INVIGILATOR_DUTY'
  AND k.COLUMN_NAME = 'INVIGILATOR_ID'
  AND k.REFERENCED_TABLE_NAME = 'INVIGILATOR'
  AND k.REFERENCED_COLUMN_NAME = 'INVIGILATOR_ID'
LIMIT 1;

SET @users_fk = NULL;
SET @users_delete_rule = NULL;
SET @users_update_rule = NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
INTO @users_fk, @users_delete_rule, @users_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'users'
  AND k.COLUMN_NAME = 'invigilator_id'
  AND k.REFERENCED_TABLE_NAME = 'INVIGILATOR'
  AND k.REFERENCED_COLUMN_NAME = 'INVIGILATOR_ID'
LIMIT 1;

SET @migration_sql = IF(
    @duty_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE `INVIGILATOR_DUTY` DROP FOREIGN KEY `', REPLACE(@duty_fk, '`', '``'), '`')
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @users_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE `users` DROP FOREIGN KEY `', REPLACE(@users_fk, '`', '``'), '`')
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

ALTER TABLE INVIGILATOR MODIFY INVIGILATOR_ID INT NOT NULL AUTO_INCREMENT;

CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    allocation_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    checked_by INT NULL,
    UNIQUE KEY uq_attendance_allocation (allocation_id),
    CONSTRAINT attendance_ibfk_1
        FOREIGN KEY (allocation_id) REFERENCES ROOM_ALLOCATION (ALLOCATION_ID)
        ON DELETE CASCADE,
    CONSTRAINT attendance_ibfk_2
        FOREIGN KEY (checked_by) REFERENCES users (user_id)
        ON DELETE SET NULL,
    CONSTRAINT chk_attendance_status CHECK (status IN ('present', 'absent'))
);

ALTER TABLE attendance
    MODIFY status VARCHAR(20) NOT NULL;

SET @migration_sql = IF(
    @duty_fk IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `INVIGILATOR_DUTY` ADD CONSTRAINT `',
        REPLACE(@duty_fk, '`', '``'),
        '` FOREIGN KEY (`INVIGILATOR_ID`) REFERENCES `INVIGILATOR` (`INVIGILATOR_ID`) ',
        'ON DELETE ', @duty_delete_rule, ' ON UPDATE ', @duty_update_rule
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @users_fk IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `users` ADD CONSTRAINT `',
        REPLACE(@users_fk, '`', '``'),
        '` FOREIGN KEY (`invigilator_id`) REFERENCES `INVIGILATOR` (`INVIGILATOR_ID`) ',
        'ON DELETE ', @users_delete_rule, ' ON UPDATE ', @users_update_rule
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Resolve any duplicates returned here before adding the unique key below.
SELECT allocation_id, COUNT(*) AS duplicate_count
FROM attendance
GROUP BY allocation_id
HAVING COUNT(*) > 1;

SET @attendance_unique_exists := (
    SELECT COUNT(*)
    FROM (
        SELECT INDEX_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'attendance'
          AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME
        HAVING COUNT(*) = 1
           AND MAX(COLUMN_NAME = 'allocation_id') = 1
    ) AS allocation_unique_indexes
);
SET @attendance_unique_sql := IF(
    @attendance_unique_exists = 0,
    'ALTER TABLE attendance ADD UNIQUE KEY uq_attendance_allocation (allocation_id)',
    'SELECT 1'
);
PREPARE attendance_unique_stmt FROM @attendance_unique_sql;
EXECUTE attendance_unique_stmt;
DEALLOCATE PREPARE attendance_unique_stmt;

CREATE TABLE IF NOT EXISTS INVIGILATOR_REQUEST (
    REQUEST_ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    FULL_NAME VARCHAR(120) NOT NULL,
    EMAIL VARCHAR(160) NOT NULL,
    PHONE VARCHAR(20),
    CLAIM_CODE_HASH VARCHAR(255) NOT NULL,
    STATUS VARCHAR(20) NOT NULL DEFAULT 'pending',
    INVIGILATOR_ID INT NULL,
    REQUESTED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    REVIEWED_AT TIMESTAMP NULL,
    KEY idx_invigilator_request_status (STATUS, REQUESTED_AT),
    CONSTRAINT chk_invigilator_request_status
        CHECK (STATUS IN ('pending', 'approved', 'rejected', 'claimed'))
);
