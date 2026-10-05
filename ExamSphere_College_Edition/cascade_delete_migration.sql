-- Run once against an existing ExamSphere database.
-- Removing an exam removes its registrations, those registrations' room
-- allocations and attendance records, and the exam's invigilator duties.
-- Student, subject, room, invigilator, and user records are retained.
ALTER TABLE exam_registration
    DROP FOREIGN KEY exam_registration_ibfk_2;
ALTER TABLE exam_registration
    ADD CONSTRAINT exam_registration_ibfk_2
        FOREIGN KEY (EXAM_ID) REFERENCES examination (EXAM_ID)
        ON DELETE CASCADE;

ALTER TABLE room_allocation
    DROP FOREIGN KEY room_allocation_ibfk_1;
ALTER TABLE room_allocation
    ADD CONSTRAINT room_allocation_ibfk_1
        FOREIGN KEY (REGISTR_ID) REFERENCES exam_registration (REGISTR_ID)
        ON DELETE CASCADE;

ALTER TABLE attendance
    DROP FOREIGN KEY attendance_ibfk_1;
ALTER TABLE attendance
    ADD CONSTRAINT attendance_ibfk_1
        FOREIGN KEY (allocation_id) REFERENCES room_allocation (ALLOCATION_ID)
        ON DELETE CASCADE;

ALTER TABLE invigilator_duty
    DROP FOREIGN KEY invigilator_duty_ibfk_1;
ALTER TABLE invigilator_duty
    ADD CONSTRAINT invigilator_duty_ibfk_1
        FOREIGN KEY (EXAM_ID) REFERENCES examination (EXAM_ID)
        ON DELETE CASCADE;
