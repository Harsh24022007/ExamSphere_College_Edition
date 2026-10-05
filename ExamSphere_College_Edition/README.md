# ExamSphere — College Edition

ExamSphere is a PHP 8+ web app for administering college examinations using MySQL 8+ or MariaDB. It includes administration pages for master data, exam registrations, allocations and invigilator duties, plus analytics, seating plans and exports.

## Run locally with XAMPP

1. Start your local MySQL/MariaDB server. Confirm its port and credentials in XAMPP or your database configuration.
2. Import `db_setup.sql` into the server. This creates the `exam_room_allocation_system` database and its tables.
3. Import `auth.sql` after the schema. It adds the demo administrator.
4. Configure the connection using environment variables, or edit the defaults in `config.php`:

   | Variable | Default |
   | --- | --- |
   | `EXAMSPHERE_DB_HOST` | `127.0.0.1` |
   | `EXAMSPHERE_DB_PORT` | `3306` |
   | `EXAMSPHERE_DB_NAME` | `exam_room_allocation_system` |
   | `EXAMSPHERE_DB_USER` | `root` |
   | `EXAMSPHERE_DB_PASS` | empty |

   The included defaults target the local MySQL instance on port `3306`. Set `EXAMSPHERE_DB_PASS` to the password for the configured database user. `auth.sql` must be run with `exam_room_allocation_system` selected (for example, select that database in phpMyAdmin before importing it). Do not put a real production password in a public source repository.
5. From the project directory, run `php -S 127.0.0.1:8000` or serve the directory through Apache.
6. Open `http://127.0.0.1:8000/`.

The database connection is used by all admin pages; the landing page also displays whether it can reach the database. XAMPP's bundled PHP needs the `pdo_mysql` extension enabled.

For an existing database, run `cascade_delete_migration.sql` once with the ExamSphere database selected. Deleting an examination then deletes its registrations, room allocations, attendance entries attached to those allocations, and invigilator duties. Student, subject, room, and invigilator records remain. The migration expects the existing extended schema's `attendance` table.

For an existing database, also run `student_id_autoincrement_migration.sql` once to let MySQL assign IDs to newly created student profiles. It preserves existing student IDs and starts new IDs after the current maximum.

Deleting a department in the admin portal removes its students and linked student accounts, subjects, examinations, relevant registrations and allocations, linked attendance, and duties for its examinations in one transaction. Registrations of those department's students in other departments' exams are removed, but those exams and their other students remain. Rooms and invigilators are retained. After deletion, the page lists deleted record IDs/names and per-table counts.

## Separate portals and student registration

- Open the site home page to choose a **Student**, **Invigilator**, or **Administrator** portal.
- Students can create an account at `student_register.php` using their name, department, current semester, username, and a password of at least 8 characters. The database automatically assigns their student ID, and the new profile and linked login are created together.
- Students sign in at `student_login.php` using the username and password chosen during registration; no student ID is requested on the sign-in page. Their portal displays their generated student ID and lists upcoming eligible exams, registered scheduled exams and seat assignments, and completed exam history. Registration is accepted only for published upcoming exams matching the student's department and current semester.
- Invigilators sign in at `invigilator_login.php`. Their portal separates upcoming assigned duties from completed duty history. Invigilator accounts and assignments are created by the administrator; invigilators cannot see other staff's duties.
- Administrators sign in at `login.php?role=admin`. The local demo account is username `admin`, password `admin123`.

Change or remove the demo account before deploying outside a local development environment.

## Features

- Separate role-protected administrator, student, and invigilator portals, with independent role-specific sign-in pages
- Administrator, student, and invigilator sign-ins use separate browser sessions, allowing different roles to stay signed in at the same time in separate tabs
- Administrators manage departments, subjects, students, rooms, examinations, invigilators, room allocations, and invigilator duties, including linked account/duty cleanup when deleting students or invigilators
- Student sign-up creates a linked student profile and login with a database-generated student ID
- Students can register only for published, future examinations matching their department and current semester; their portal separates eligible exams, scheduled registrations and completed exam history
- Invigilators can view only examination duties assigned to their linked profile, separated into scheduled and completed history
- Create, search, edit and delete operations for all supplied tables
- Foreign-key dropdowns in related record forms to ensure referenced parent records exist before inserting dependent records
- Department analytics
- Manual seat assignment with room-capacity and occupied-seat checks
- Automatic allocation that preserves assignments and only uses free seats in available rooms
- Visual seating plan with drag-and-drop seat moves saved to MySQL
- Printable reports and CSV / Excel-compatible exports

MySQL does not support cascading inserts; dependent records must reference a parent that already exists. The admin forms provide choices from existing parent records and the `db_setup.sql` constraints continue to reject invalid references. Registration records are student-owned and read-only in the admin portal; students create their own registrations after the server rechecks eligibility.

User accounts in the existing database use the `users` table. Older `pbkdf2_sha256` password hashes are verified on sign-in and transparently upgraded to PHP's current password hash format after a successful login. New student accounts are created by student sign-up; invigilator accounts are provisioned by an administrator.

The supplied schema uses `UNIQUE(ROOM_ID, SEAT_NO)`, so a room/seat pair is considered occupied globally across exams. The allocation manager and visual seating page reflect that constraint.

## Reports

Use **Print / Save PDF** in Reports & Exports and choose **Save as PDF** in the browser print dialog. The Excel-compatible `.xls` export is generated directly by PHP.

## Database tables

The fresh-install schema includes `DEPARTMENT`, `SUBJECT`, `STUDENTS`, `EXAMINATION`, `ROOMS`, `INVIGILATOR`, `EXAM_REGISTRATION`, `ROOM_ALLOCATION`, `INVIGILATOR_DUTY`, `users`, and `ADMIN_USERS`. Existing deployments may also have `attendance` and other application-specific tables.
