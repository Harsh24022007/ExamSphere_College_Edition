-- Run once against an existing ExamSphere database.
-- Existing examination IDs are preserved. Generated IDs are used for new exams.

-- Review any duty key collisions or references before changing the key format.
SELECT CONCAT(
    LENGTH(EXAM_ID), ':', EXAM_ID, ':', INVIGILATOR_ID, ':',
    LENGTH(ROOM_ID), ':', ROOM_ID
) AS generated_duty_id, COUNT(*) AS duty_count
FROM INVIGILATOR_DUTY
GROUP BY generated_duty_id
HAVING COUNT(*) > 1;

SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME = 'INVIGILATOR_DUTY'
  AND k.REFERENCED_COLUMN_NAME = 'DUTY_ID';

-- Preserve the existing foreign-key names and actions while widening EXAM_ID.
SET @registration_exam_fk = NULL;
SET @registration_delete_rule = NULL;
SET @registration_update_rule = NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
INTO @registration_exam_fk, @registration_delete_rule, @registration_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'EXAM_REGISTRATION'
  AND k.COLUMN_NAME = 'EXAM_ID'
  AND k.REFERENCED_TABLE_NAME = 'EXAMINATION'
  AND k.REFERENCED_COLUMN_NAME = 'EXAM_ID'
LIMIT 1;

SET @duty_exam_fk = NULL;
SET @duty_delete_rule = NULL;
SET @duty_update_rule = NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
INTO @duty_exam_fk, @duty_delete_rule, @duty_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'INVIGILATOR_DUTY'
  AND k.COLUMN_NAME = 'EXAM_ID'
  AND k.REFERENCED_TABLE_NAME = 'EXAMINATION'
  AND k.REFERENCED_COLUMN_NAME = 'EXAM_ID'
LIMIT 1;

SET @migration_sql = IF(
    @registration_exam_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE `EXAM_REGISTRATION` DROP FOREIGN KEY `', REPLACE(@registration_exam_fk, '`', '``'), '`')
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @duty_exam_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE `INVIGILATOR_DUTY` DROP FOREIGN KEY `', REPLACE(@duty_exam_fk, '`', '``'), '`')
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

ALTER TABLE EXAMINATION MODIFY EXAM_ID VARCHAR(191) NOT NULL;
ALTER TABLE EXAM_REGISTRATION MODIFY EXAM_ID VARCHAR(191) NOT NULL;
ALTER TABLE INVIGILATOR_DUTY MODIFY EXAM_ID VARCHAR(191) NOT NULL;
ALTER TABLE INVIGILATOR_DUTY MODIFY DUTY_ID VARCHAR(300) NOT NULL;

UPDATE INVIGILATOR_DUTY
SET DUTY_ID = CONCAT(
    LENGTH(EXAM_ID), ':', EXAM_ID, ':', INVIGILATOR_ID, ':',
    LENGTH(ROOM_ID), ':', ROOM_ID
);

SET @migration_sql = IF(
    @registration_exam_fk IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `EXAM_REGISTRATION` ADD CONSTRAINT `',
        REPLACE(@registration_exam_fk, '`', '``'),
        '` FOREIGN KEY (`EXAM_ID`) REFERENCES `EXAMINATION` (`EXAM_ID`) ',
        'ON DELETE ', @registration_delete_rule, ' ON UPDATE ', @registration_update_rule
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @duty_exam_fk IS NULL,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `INVIGILATOR_DUTY` ADD CONSTRAINT `',
        REPLACE(@duty_exam_fk, '`', '``'),
        '` FOREIGN KEY (`EXAM_ID`) REFERENCES `EXAMINATION` (`EXAM_ID`) ',
        'ON DELETE ', @duty_delete_rule, ' ON UPDATE ', @duty_update_rule
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
