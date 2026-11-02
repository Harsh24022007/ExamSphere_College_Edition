-- Run once against an existing ExamSphere database.
-- ASSIGNED_ON is not used by the duty workflow; exam dates/times live on EXAMINATION.
SET @assigned_on_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'INVIGILATOR_DUTY'
      AND COLUMN_NAME = 'ASSIGNED_ON'
);
SET @migration_sql := IF(
    @assigned_on_exists > 0,
    'ALTER TABLE `INVIGILATOR_DUTY` DROP COLUMN `ASSIGNED_ON`',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
