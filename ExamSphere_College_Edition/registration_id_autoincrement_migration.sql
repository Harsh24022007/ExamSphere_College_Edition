USE exam_room_allocation_system;

-- Existing registration IDs must be numeric and fit a signed INT. Review this
-- query first; resolve any returned rows before continuing with the migration.
SELECT REGISTR_ID
FROM EXAM_REGISTRATION
WHERE REGISTR_ID NOT REGEXP '^[0-9]+$'
   OR CAST(REGISTR_ID AS UNSIGNED) > 2147483647;

SELECT CAST(REGISTR_ID AS UNSIGNED) AS converted_id, COUNT(*) AS id_count
FROM EXAM_REGISTRATION
GROUP BY CAST(REGISTR_ID AS UNSIGNED)
HAVING COUNT(*) > 1;

SELECT ALLOCATION_ID
FROM ROOM_ALLOCATION
WHERE REGISTR_ID IS NULL;

-- Change the referencing column and restore its foreign key after converting
-- the parent ID. Existing numeric registration IDs and allocations are kept.
SET @registration_fk = NULL;
SELECT CONSTRAINT_NAME
INTO @registration_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'ROOM_ALLOCATION'
  AND COLUMN_NAME = 'REGISTR_ID'
  AND REFERENCED_TABLE_NAME = 'EXAM_REGISTRATION'
LIMIT 1;

SET @migration_sql = IF(
  @registration_fk IS NULL,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE `ROOM_ALLOCATION` DROP FOREIGN KEY `',
    REPLACE(@registration_fk, '`', '``'),
    '`'
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

ALTER TABLE ROOM_ALLOCATION
  MODIFY REGISTR_ID INT NOT NULL;

ALTER TABLE EXAM_REGISTRATION
  MODIFY REGISTR_ID INT NOT NULL AUTO_INCREMENT;

ALTER TABLE ROOM_ALLOCATION
  ADD CONSTRAINT fk_room_allocation_registration
  FOREIGN KEY (REGISTR_ID)
  REFERENCES EXAM_REGISTRATION (REGISTR_ID)
  ON DELETE CASCADE;

-- Preserve and temporarily remove an optional attendance foreign key that
-- references ALLOCATION_ID while enabling AUTO_INCREMENT on that column.
SET @attendance_fk = NULL;
SET @attendance_delete_rule = NULL;
SET @attendance_update_rule = NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
INTO @attendance_fk, @attendance_delete_rule, @attendance_update_rule
FROM information_schema.KEY_COLUMN_USAGE AS k
JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'attendance'
  AND k.COLUMN_NAME = 'allocation_id'
  AND k.REFERENCED_TABLE_NAME = 'ROOM_ALLOCATION'
  AND k.REFERENCED_COLUMN_NAME = 'ALLOCATION_ID'
LIMIT 1;

SET @migration_sql = IF(
  @attendance_fk IS NULL,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE `attendance` DROP FOREIGN KEY `',
    REPLACE(@attendance_fk, '`', '``'),
    '`'
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

ALTER TABLE ROOM_ALLOCATION
  MODIFY ALLOCATION_ID INT NOT NULL AUTO_INCREMENT;

SET @migration_sql = IF(
  @attendance_fk IS NULL,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE `attendance` ADD CONSTRAINT `',
    REPLACE(@attendance_fk, '`', '``'),
    '` FOREIGN KEY (`allocation_id`) REFERENCES `ROOM_ALLOCATION` (`ALLOCATION_ID`) ',
    'ON DELETE ', @attendance_delete_rule, ' ON UPDATE ', @attendance_update_rule
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;