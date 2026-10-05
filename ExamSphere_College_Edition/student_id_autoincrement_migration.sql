USE exam_room_allocation_system;

ALTER TABLE exam_registration
    DROP FOREIGN KEY exam_registration_ibfk_1;

ALTER TABLE users
    DROP FOREIGN KEY users_ibfk_1;

ALTER TABLE STUDENTS
    MODIFY STUDENT_ID INT NOT NULL AUTO_INCREMENT;

ALTER TABLE exam_registration
    ADD CONSTRAINT exam_registration_ibfk_1
    FOREIGN KEY (STUDENT_ID) REFERENCES STUDENTS(STUDENT_ID);

ALTER TABLE users
    ADD CONSTRAINT users_ibfk_1
    FOREIGN KEY (student_id) REFERENCES STUDENTS(STUDENT_ID);
