-- ExamSphere — optional SQL joins and aggregates demonstration
--
-- This file is intentionally separate from the running PHP application.
-- It contains SELECT-only examples and does not create, alter, insert, update,
-- or delete any data. Run it with the exam_room_allocation_system database
-- selected after importing db_setup.sql (or examsphere_schema.sql).
--
-- Supported by MySQL 8+ and MariaDB.

USE exam_room_allocation_system;

-- ============================================================================
-- 1. INNER JOIN
-- ============================================================================
-- An unqualified JOIN is an INNER JOIN. Only exams that have a subject appear.
SELECT
    e.EXAM_ID,
    e.EXAM_DATE,
    s.SUBJECT_NAME
FROM EXAMINATION AS e
INNER JOIN SUBJECT AS s
    ON s.SUBJECT_ID = e.SUBJECT_ID
ORDER BY e.EXAM_DATE, e.EXAM_ID;

-- ============================================================================
-- 2. LEFT JOIN
-- ============================================================================
-- Includes every department, including departments with no students.
SELECT
    d.DEPT_ID,
    d.DEPT_NAME,
    st.STUDENT_ID
FROM DEPARTMENT AS d
LEFT JOIN STUDENTS AS st
    ON st.DEPT_ID = d.DEPT_ID
ORDER BY d.DEPT_ID, st.STUDENT_ID;

-- ============================================================================
-- 3. RIGHT JOIN
-- ============================================================================
-- Includes every room, even rooms with no allocation. This is the same
-- relationship as a LEFT JOIN with the table order reversed.
SELECT
    r.ROOM_ID,
    r.BUILDING,
    ra.ALLOCATION_ID,
    ra.SEAT_NO
FROM ROOM_ALLOCATION AS ra
RIGHT JOIN ROOMS AS r
    ON r.ROOM_ID = ra.ROOM_ID
ORDER BY r.ROOM_ID, ra.SEAT_NO;

-- ============================================================================
-- 4. FULL OUTER JOIN equivalent for MySQL/MariaDB
-- ============================================================================
-- MySQL does not provide FULL OUTER JOIN directly. UNION ALL combines:
--   a) all departments and matching students, and
--   b) students that did not match the first side.
SELECT
    d.DEPT_ID,
    d.DEPT_NAME,
    st.STUDENT_ID,
    CONCAT_WS(' ', st.FNAME, st.MNAME, st.LNAME) AS STUDENT_NAME
FROM DEPARTMENT AS d
LEFT JOIN STUDENTS AS st
    ON st.DEPT_ID = d.DEPT_ID

UNION ALL

SELECT
    d.DEPT_ID,
    d.DEPT_NAME,
    st.STUDENT_ID,
    CONCAT_WS(' ', st.FNAME, st.MNAME, st.LNAME) AS STUDENT_NAME
FROM STUDENTS AS st
LEFT JOIN DEPARTMENT AS d
    ON d.DEPT_ID = st.DEPT_ID
WHERE d.DEPT_ID IS NULL

ORDER BY DEPT_ID, STUDENT_ID;

-- ============================================================================
-- 5. CROSS JOIN
-- ============================================================================
-- Produces department/room combinations. LIMIT keeps the demonstration output
-- manageable if the database contains many departments and rooms.
SELECT
    d.DEPT_NAME,
    r.ROOM_ID,
    r.BUILDING
FROM DEPARTMENT AS d
CROSS JOIN ROOMS AS r
ORDER BY d.DEPT_ID, r.ROOM_ID
LIMIT 20;

-- ============================================================================
-- 6. SELF JOIN
-- ============================================================================
-- Compares rooms in the same building without pairing a room with itself or
-- returning the same pair twice.
SELECT
    r1.BUILDING,
    r1.ROOM_ID AS ROOM_ONE,
    r2.ROOM_ID AS ROOM_TWO,
    r1.CAPACITY AS ROOM_ONE_CAPACITY,
    r2.CAPACITY AS ROOM_TWO_CAPACITY
FROM ROOMS AS r1
INNER JOIN ROOMS AS r2
    ON r1.BUILDING = r2.BUILDING
   AND r1.ROOM_ID < r2.ROOM_ID
ORDER BY r1.BUILDING, r1.ROOM_ID, r2.ROOM_ID;

-- ============================================================================
-- 7. Aggregate functions: COUNT, SUM, AVG, MIN, MAX
-- ============================================================================
-- One row summarizes the room inventory. COALESCE makes an empty table return
-- zero counts instead of NULL for the count-like values.
SELECT
    COUNT(*) AS total_rooms,
    COALESCE(SUM(CASE WHEN STATUS = 'AVAILABLE' THEN 1 ELSE 0 END), 0)
        AS available_rooms,
    COALESCE(AVG(CAPACITY), 0) AS average_capacity,
    COALESCE(MIN(CAPACITY), 0) AS minimum_capacity,
    COALESCE(MAX(CAPACITY), 0) AS maximum_capacity
FROM ROOMS;

-- ============================================================================
-- 8. GROUP BY with AVG, MIN, MAX and HAVING
-- ============================================================================
-- Summarizes room capacity by building and returns only buildings whose
-- average capacity is at least 20 seats.
SELECT
    BUILDING,
    COUNT(*) AS room_count,
    AVG(CAPACITY) AS average_capacity,
    MIN(CAPACITY) AS minimum_capacity,
    MAX(CAPACITY) AS maximum_capacity
FROM ROOMS
GROUP BY BUILDING
HAVING AVG(CAPACITY) >= 20
ORDER BY average_capacity DESC, BUILDING;

-- ============================================================================
-- 9. Aggregate report using joins
-- ============================================================================
-- Demonstrates COUNT(DISTINCT), AVG, MIN, MAX and HAVING together while
-- retaining departments that have no registrations through LEFT JOINs.
SELECT
    d.DEPT_ID,
    d.DEPT_NAME,
    COUNT(DISTINCT st.STUDENT_ID) AS student_count,
    COUNT(DISTINCT er.REGISTR_ID) AS registration_count,
    AVG(r.CAPACITY) AS average_room_capacity,
    MIN(r.CAPACITY) AS smallest_assigned_room,
    MAX(r.CAPACITY) AS largest_assigned_room
FROM DEPARTMENT AS d
LEFT JOIN STUDENTS AS st
    ON st.DEPT_ID = d.DEPT_ID
LEFT JOIN EXAM_REGISTRATION AS er
    ON er.STUDENT_ID = st.STUDENT_ID
LEFT JOIN ROOM_ALLOCATION AS ra
    ON ra.REGISTR_ID = er.REGISTR_ID
LEFT JOIN ROOMS AS r
    ON r.ROOM_ID = ra.ROOM_ID
GROUP BY d.DEPT_ID, d.DEPT_NAME
HAVING COUNT(DISTINCT st.STUDENT_ID) > 0
ORDER BY student_count DESC, d.DEPT_ID;
