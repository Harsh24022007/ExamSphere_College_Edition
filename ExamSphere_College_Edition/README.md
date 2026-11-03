# ExamSphere — College Edition

ExamSphere is a server-rendered web application for managing college examination operations. Students can create accounts, register for eligible examinations, and check their schedules and seats. Invigilators can claim an approved account, view assigned duties, and record attendance. Administrators manage academic and examination data, seating, staff assignments, analytics, and reports.

The application is built with PHP 8+, PDO, MySQL 8+ or MariaDB, HTML, CSS, and vanilla JavaScript. Its source schema is `db_setup.sql`, with the demo administrator added by `auth.sql`. The project has three role-specific portals and a relational model linking departments, subjects, students, examinations, rooms, registrations, allocations, invigilators, duties, accounts, applications, and attendance.

**Project authors credited in the application:** Harshith S H and K Sudhanva Datta.

For a report-writing starting point, see [PROJECT_REPORT.md](PROJECT_REPORT.md) and [the detailed project report](../outputs/ExamSphere_Detailed_Project_Report.md). Read this README and the root-level SQL files alongside those reports: the SQL files below are authoritative for the current database schema. Some supplemental ER/schema artifacts in `../outputs/` may not reflect the latest attendance and invigilator-request tables.

## Run locally with XAMPP

1. Start your local MySQL/MariaDB server. Confirm its port and credentials in XAMPP or your database configuration.
2. Import `db_setup.sql` into the server. This creates the `exam_room_allocation_system` database and its tables.
3. Import `auth.sql` after the schema. It adds the demo administrator.
4. Configure the connection using environment variables, or edit the defaults in `config.php`:

   | Variable | Local development value |
   | --- | --- |
   | `EXAMSPHERE_DB_HOST` | `127.0.0.1` (recommended for local use) |
   | `EXAMSPHERE_DB_PORT` | `3306` |
   | `EXAMSPHERE_DB_NAME` | `exam_room_allocation_system` |
   | `EXAMSPHERE_DB_USER` | `root` or your configured database user |
   | `EXAMSPHERE_DB_PASS` | Set locally to your database password; do not commit it |

   `config.php` also has local development defaults; check and override them for your machine. In particular, do not assume its default host or password matches your MySQL installation. Set `EXAMSPHERE_DB_PASS` to the password for the configured database user. `auth.sql` must be run with `exam_room_allocation_system` selected (for example, select that database in phpMyAdmin before importing it). Do not put database credentials in a public source repository.
5. From the project directory, run `php -S 127.0.0.1:8000` or serve the directory through Apache.
6. Open `http://127.0.0.1:8000/`.

The database connection is used by all admin pages; the landing page also displays whether it can reach the database. XAMPP's bundled PHP needs the `pdo_mysql` extension enabled.

On Windows, the PHP CLI command can also be run from the project directory with XAMPP's PHP executable:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t .
```

Keep that terminal open while using the site. This starts PHP's development server; it is intended for local development, not public production hosting.

Subject, examination, and room IDs are text identifiers, so values such as `CSE401`, `PSP101`, and `LH1` are supported by the fresh-install schema.

For an existing database, also run `student_id_autoincrement_migration.sql` once to let MySQL assign IDs to newly created student profiles. It preserves existing student IDs and starts new IDs after the current maximum.

For an existing database, run `registration_id_autoincrement_migration.sql` once to enable generated registration and room-allocation IDs. It preserves existing numeric registration IDs and allocation records, updates the registration foreign key to the matching integer type, and preserves any attendance foreign key while enabling allocation IDs. Review the migration's preflight queries and resolve any nonnumeric, out-of-range, or duplicate-converted registration IDs and any allocations without a registration before continuing.

For an existing database, run `attendance_invigilator_approval_migration.sql` once. It enables generated invigilator profile IDs, creates the attendance and invigilator-request tables when missing, and enforces one attendance record per seat allocation. Resolve any duplicates reported by its preflight query before adding the unique key. Run this migration before `cascade_delete_migration.sql` so the attendance table is present.

For an existing database, run `generated_exam_duty_ids_migration.sql` once to widen examination IDs and convert existing duty IDs to composite keys. Review its preflight results; do not proceed if duplicate generated duty keys or foreign keys referencing `INVIGILATOR_DUTY.DUTY_ID` are returned.

For an existing database, run `remove_assigned_on_migration.sql` once to remove the unused invigilator-duty assignment date. Examination dates and times remain on `EXAMINATION`.

For an existing database, run `cascade_delete_migration.sql` once with the ExamSphere database selected. Deleting an examination then deletes its registrations, room allocations, attendance entries attached to those allocations, and invigilator duties. Student, subject, room, and invigilator records remain.

Deleting a department in the admin portal removes its students and linked student accounts, subjects, examinations, relevant registrations and allocations, linked attendance, and duties for its examinations in one transaction. Registrations of those department's students in other departments' exams are removed, but those exams and their other students remain. Rooms and invigilators are retained. After deletion, the page lists deleted record IDs/names and per-table counts.

## Separate portals and student registration

- Open the site home page to choose a **Student**, **Invigilator**, or **Administrator** portal.
- Students can create an account at `student_register.php` using their name, department, current semester, username, and a password of at least 8 characters. The database automatically assigns their student ID, and the new profile and linked login are created together.
- Students sign in at `student_login.php` using the username and password chosen during registration; no student ID is requested on the sign-in page. Their portal displays their generated student ID and lists upcoming eligible exams, registered scheduled exams and seat assignments, and completed exam history. Registration is accepted only for published upcoming exams matching the student's department and current semester.
- Invigilators first submit a request at `invigilator_register.php`. An administrator reviews and approves the request; the applicant then uses the one-time claim code displayed after requesting to choose a username and password. Invigilators sign in at `invigilator_login.php`, see only their assigned duties, and can mark students present or absent after the exam starts.
- Administrators sign in at `login.php?role=admin`. The local demo account is username `admin`, password `admin123`.

Change or remove the demo account before deploying outside a local development environment.

## Features

- Separate role-protected administrator, student, and invigilator portals, with independent role-specific sign-in pages
- Administrator, student, and invigilator sign-ins use separate browser sessions, allowing different roles to stay signed in at the same time in separate tabs
- The admin navigation sidebar can be hidden or restored with the menu button in the top bar. On desktop, the preference is remembered in the browser; on smaller screens, the same button opens or closes the navigation drawer.
- Administrators manage departments, subjects, rooms, examinations, room allocations, and invigilator duties. Student and invigilator profiles can be deleted but not manually created or edited in admin; invigilator profiles are created through the approval workflow, and assignments remain managed by the administrator.
- New examination IDs are generated from the subject ID, subject-name slug, and exam date; repeated exams for one subject on a date get a sequence suffix. Duty IDs are generated as unambiguous composite keys from the exam, invigilator, and room.
- Administrators review invigilator applications and get an attendance summary by examination. Students can see their recorded attendance in their portal.
- Student sign-up creates a linked student profile and login with a database-generated student ID
- Students can register only for published, future examinations matching their department and current semester; their portal separates eligible exams, scheduled registrations and completed exam history
- Invigilators can view only examination duties assigned to their linked profile, mark allocated students present or absent once an exam begins, and review scheduled and completed duties.
- Create, search, edit and delete operations for administrative master-data and assignment tables; student profiles are delete-only and invigilator profiles are approval-created and delete-only
- Foreign-key dropdowns in related record forms to ensure referenced parent records exist before inserting dependent records
- Department analytics
- Manual seat assignment with room-capacity and occupied-seat checks
- Automatic allocation that preserves assignments and only uses free seats in available rooms
- Visual seating plan with drag-and-drop seat moves saved to MySQL
- Printable reports and CSV / Excel-compatible exports
- Administrator Exam Operations dashboard uses joined registration, room, invigilator-duty and attendance data to find unallocated students and rooms needing invigilator coverage, with direct links to fix them

MySQL does not support cascading inserts; dependent records must reference a parent that already exists. The admin forms provide choices from existing parent records and the `db_setup.sql` constraints continue to reject invalid references. Registration records are student-owned and read-only in the admin portal; students create their own registrations after the server rechecks eligibility.

User accounts in the existing database use the `users` table. Older `pbkdf2_sha256` password hashes are verified on sign-in and transparently upgraded to PHP's current password hash format after a successful login. New student accounts are created by student sign-up; invigilator accounts are enabled only after administrator approval and applicant claim.

The supplied schema uses `UNIQUE(ROOM_ID, SEAT_NO)`, so a room/seat pair is considered occupied globally across exams. The allocation manager and visual seating page reflect that constraint.

## Reports

Use **Print / Save PDF** in Reports & Exports and choose **Save as PDF** in the browser print dialog. The Excel-compatible `.xls` export is generated directly by PHP.

See [PROJECT_REPORT.md](PROJECT_REPORT.md) for the project overview, architecture, implementation, technology stack, and local demo screenshots.

## SQL joins and aggregate demonstrations

The administrator **Exam Operations** page uses joins and aggregates for operational work: it summarizes exam registrations, seat allocation, attendance, room capacity and invigilator coverage, then links directly to the allocation, seating, report and duty-assignment actions. The optional [sql_joins_aggregates_demo.sql](sql_joins_aggregates_demo.sql) file remains available for read-only SQL examples.

## Database tables

The fresh-install schema across `db_setup.sql` and `auth.sql` includes `DEPARTMENT`, `SUBJECT`, `STUDENTS`, `EXAMINATION`, `ROOMS`, `INVIGILATOR`, `EXAM_REGISTRATION`, `ROOM_ALLOCATION`, `INVIGILATOR_DUTY`, `users`, `attendance`, `INVIGILATOR_REQUEST`, and `ADMIN_USERS`.

## Project overview

### Problem and goals

ExamSphere centralizes examination data that might otherwise be spread across spreadsheets and manual processes. The project aims to:

- Keep academic, examination, room, and user records connected in one relational database.
- Separate administrator, student, and invigilator tasks behind role-specific sign-in and authorization.
- Apply department and semester eligibility rules when students register.
- Reduce duplicate registrations and invalid or conflicting seat assignments.
- Help administrators prepare staffing, attendance, seating, analytics, and printable reports.
- Demonstrate relational database design, constraints, joins, aggregates, and transactional workflows.

### User roles and main workflows

| Role | Main workflow |
| --- | --- |
| Visitor | Open the landing page, check the database status, and choose a portal. |
| Student | Create an account and student profile; sign in; view eligible published exams; register; review scheduled exams, seat assignments, completed exams, and recorded attendance. |
| Invigilator | Submit an access request; wait for administrator review; claim the approved profile with the one-time request code; sign in; view assigned duties; record allocated students as present or absent after the exam begins. |
| Administrator | Sign in; manage academic and operational records; review invigilator applications; prepare registrations, allocations, and duties; review exam readiness, attendance, and analytics; print or export reports. |

### Core business rules

- Student registration is restricted to published examinations that have not ended and whose subject department and offered semester match the student's profile.
- A student can register for an examination only once. The server rechecks eligibility when the registration is submitted; it does not rely on the browser's exam list alone.
- A registration can receive at most one room allocation. Automatic allocation keeps existing allocations and uses remaining seats in available rooms; manual and visual allocation check capacity and occupancy.
- The fresh schema's `UNIQUE (ROOM_ID, SEAT_NO)` constraint treats a seat as occupied across all examinations, not only one exam. This affects the allocation and seating interfaces.
- An invigilator request must be approved before an invigilator profile can be claimed and used to sign in. The administrator's approval and rejection actions are transactional.
- Attendance is recorded against a seat allocation and supports `present` and `absent` statuses. Assigned invigilators can mark attendance after the examination starts.
- Examination deletion cascades to its registrations, allocations (through registrations), and invigilator duties. Department deletion in the application performs broader dependent-record cleanup in a transaction.

## Architecture and technology

```text
Browser (administrator / student / invigilator)
              |
              v
PHP page controllers and shared page templates
              |
     config.php / PDO
              |
              v
       MySQL 8+ / MariaDB
```

- **Presentation:** PHP-rendered HTML, responsive CSS, and vanilla JavaScript for interface behavior, including the visual seating page.
- **Application:** PHP page controllers implement authentication, role checks, validation, workflows, reports, and database operations.
- **Database:** MySQL or MariaDB relational tables, foreign keys, unique constraints, and selected transactions protect relationships and workflows.
- **Data access:** PDO with exception-based error reporting, associative fetches, and emulated prepares disabled.
- **Exports:** Browser print/Save as PDF, CSV, and an Excel-compatible `.xls` response generated as an HTML table.
- **Local runtime:** XAMPP or a PHP CLI development server plus a running MySQL/MariaDB server.
- **External dependencies:** The application uses PHP's built-in extensions and browser technologies; no Composer or npm install step is documented for the current app.

## Application file guide

| File | Responsibility |
| --- | --- |
| `index.php` | Public landing page, portal links, database status, and live student/room/exam counts. |
| `config.php` | Database connection, role-specific sessions, authorization helpers, escaping, password compatibility, and CSRF helpers. |
| `login.php`, `logout.php` | Shared sign-in controller and sign-out. |
| `student_login.php`, `student_register.php`, `student_portal.php` | Student sign-in, transactional profile/account creation, exam eligibility/registration, schedule, and history. |
| `invigilator_login.php`, `invigilator_register.php`, `invigilator_portal.php`, `invigilator_requests.php` | Invigilator access request, administrator review, account claim/sign-in, duty schedule, and attendance actions. |
| `dashboard.php` | Administrator summaries, upcoming exams, application requests, and attendance overview. |
| `manage.php` | Allowlisted administrator create/search/edit/delete interface for supported tables and foreign-key selections. Student exam registrations are student-owned and read-only here. |
| `allocation.php` | Manual and automatic room/seat assignment. |
| `seating.php` | Visual room plan, occupied-seat view, drag-and-drop seat moves, and print action. |
| `sql_lab.php` | Administrator-only exam-readiness view using joins and aggregates to highlight unallocated students and rooms without invigilator coverage. |
| `analytics.php` | Department-level summary and aggregates. |
| `reports.php`, `export.php` | Printable seating report and CSV/Excel-compatible downloads. |
| `partials.php`, `portal_header.php`, `portal_footer.php`, `end.php` | Shared administrator and portal page layout. The administrator top bar includes the sidebar toggle. |
| `assets/app.js` | Shared browser interactions, including the responsive admin sidebar toggle and saved desktop preference. |
| `assets/admin-sidebar.css` | Admin-only sidebar hide/show transitions and toggle-button styles. |
| `assets/` | Stylesheets and JavaScript for the site. |
| `db_setup.sql`, `auth.sql` | Canonical fresh-install database schema and demo administrator. |
| `*_migration.sql` | One-time migrations for existing databases; read each script and its preflight guidance before running. |
| `sql_joins_aggregates_demo.sql` | Optional standalone, read-only SQL examples; separate from application runtime. |
| `demo_images/` | Screenshots for the short project report. |
| `PROJECT_REPORT.md` | Short report with project summary, stack, screenshots, and verification notes. |
| `../outputs/ExamSphere_Detailed_Project_Report.md` | Longer report draft with objectives, design, module analysis, risks, testing ideas, and future work. |

## Database design and relationships

`db_setup.sql` creates the core application tables. `auth.sql` adds the separate administrator login table and inserts the local demo administrator.

| Table | Purpose and key relationships |
| --- | --- |
| `DEPARTMENT` | Department master; parent of `SUBJECT` and `STUDENTS`. |
| `SUBJECT` | Subject name, offered semester, and owning department; parent of `EXAMINATION`. |
| `STUDENTS` | Student profile, department, and current semester. |
| `EXAMINATION` | Subject, date, time range, and published flag. |
| `ROOMS` | Room/building/floor, capacity, and availability status. |
| `INVIGILATOR` | Invigilator profile and contact details. |
| `EXAM_REGISTRATION` | Associative entity between a student and an examination; unique per student/exam. |
| `ROOM_ALLOCATION` | At most one assigned room/seat per registration. |
| `INVIGILATOR_DUTY` | Associates an examination and room with an assigned invigilator. |
| `users` | Student/invigilator sign-in accounts and optional linked profiles; also supports the unified account lookup path. |
| `attendance` | Present/absent mark for a room allocation and the user who checked it. |
| `INVIGILATOR_REQUEST` | Applicant contact details, hashed claim code, review status, and approved profile reference. |
| `ADMIN_USERS` | Administrator credentials used by the seeded demo administrator. |

The core relationship flow is:

```text
DEPARTMENT ──< SUBJECT ──< EXAMINATION
     │                         │
     └──< STUDENTS ──< EXAM_REGISTRATION >──┘
                                  │
                                  └── ROOM_ALLOCATION >── ROOMS
                                          │
                                          └── attendance

EXAMINATION ──< INVIGILATOR_DUTY >── INVIGILATOR
                         │
                         └── ROOMS

STUDENTS / INVIGILATOR ── optional one-to-one profile links ── users
INVIGILATOR_REQUEST ── reviewed and claimed through the invigilator portal
```

Key database constraints include primary keys, foreign keys, unique usernames, unique student/exam registrations, one allocation per registration, unique room/seat pairs, and unique exam/invigilator/room duties. `users.role` is restricted to supported roles. Examination deletion has the cascades described above; deletion workflows for some other parent records are implemented in application transactions.

### Administrator and account model

The schema has both `ADMIN_USERS` and `users`. The seeded administrator in `auth.sql` is stored in `ADMIN_USERS`; student and claimed invigilator accounts are stored in `users`. `login.php` checks the administrator table first, then the unified users table. Do not assume every admin account is stored in `users`.

## Security and integrity controls

The current implementation includes:

- Server-side role and account-profile checks on protected portal pages.
- Distinct role session names, session ID regeneration on successful login, strict session mode, and HTTP-only/SameSite cookies.
- CSRF tokens on state-changing forms.
- Prepared statements for parameterized database operations.
- HTML escaping for rendered values.
- PHP password hashing and password verification, including compatibility with and rehashing of supported legacy user hashes.
- Transactions around important workflows such as student account creation, student exam registration, invigilator review/approval, and department deletion.
- Database foreign keys and uniqueness constraints, plus application-side checks for eligibility, capacity, and seat occupancy.

These controls do not make this a production-ready deployment. Before any real deployment, replace demo credentials, keep database secrets out of source control, enable HTTPS, review database permissions and server configuration, and add appropriate operational monitoring, backup, access, and abuse-prevention controls. Do not expose PHP's built-in server to the public internet.

## Database setup notes

### Fresh installation

1. Back up or choose a clean local MySQL/MariaDB instance.
2. Import `db_setup.sql`.
3. Select `exam_room_allocation_system`, then import `auth.sql`.
4. Configure the database host, port, name, username, and password in `config.php` or via the `EXAMSPHERE_DB_*` environment variables.
5. Confirm PHP has both `pdo` and `pdo_mysql` enabled.
6. Start the website using the local development command above and open `http://127.0.0.1:8000/`.
7. Sign in to the local demo administrator account (`admin` / `admin123`) to verify the admin portal. Change/remove this demo account before sharing or deploying the system.

The fresh schema already defines `attendance` and `INVIGILATOR_REQUEST`. The upgrade migrations are for existing installations, not steps to repeat on top of a fresh install. A fresh schema does not include sample departments, students, exams, or rooms; populate required parent records before creating related records.

### Existing installation and migrations

Do not run every migration blindly. First back up the database, inspect the target schema, and read the complete migration and its comments/preflight queries. Each migration is intended for a specific older schema and generally should be run once:

- `student_id_autoincrement_migration.sql`: enables generated IDs for new student profiles while preserving existing IDs.
- `registration_id_autoincrement_migration.sql`: converts registration and allocation IDs to generated integer IDs; resolve all preflight findings before proceeding.
- `attendance_invigilator_approval_migration.sql`: upgrades invigilator IDs and creates/updates attendance support; resolve any duplicate attendance allocations before enforcing uniqueness.
- `generated_exam_duty_ids_migration.sql`: widens exam IDs and converts existing invigilator duty identifiers; heed its duplicate-key and foreign-key preflight checks.
- `remove_assigned_on_migration.sql`: removes the unused duty assignment date.
- `cascade_delete_migration.sql`: adds examination/registration deletion cascades; run it only after the attendance prerequisites it documents are satisfied.

The attendance/invigilator-approval migration must precede the cascade-delete migration when both are needed. Do not apply a migration to a new installation just because it is present in the repository.

## Reports, screenshots, and SQL demonstrations

- Use **Print / Save PDF** in `reports.php` and choose **Save as PDF** in the browser's print dialog.
- `export.php` downloads the seating list as CSV or Excel-compatible `.xls`. Spreadsheet-oriented fields are guarded against formula interpretation.
- `sql_lab.php` performs operational readiness summaries, including registration/allocation/attendance totals and invigilator coverage by room.
- `sql_joins_aggregates_demo.sql` is a separate set of SELECT-only examples; it is not required for normal application use.
- Screenshot assets in `demo_images/` can be included in a written report. `PROJECT_REPORT.md` already embeds the project's screenshot set.

Suggested report sections: abstract/executive summary; problem statement; objectives and scope; user roles and requirements; architecture and technology stack; module descriptions; database/ER model and integrity rules; key workflows; interface screenshots; test plan and observed verification results; security, limitations, and future enhancements; conclusion.

When writing test results, distinguish tests actually executed from suggested test cases. The reports in this repository contain historical local verification notes; rerun the specific checks on the target machine before presenting those observations as current results. In particular, use `db_setup.sql` and `auth.sql` for the current schema rather than relying on a generated schema diagram or schema export that may predate the latest tables.

## Limitations and future work

The current system focuses on examination administration, registration, seating, staffing, and attendance. It does not implement results/marks, fees, payment, email/SMS notifications, multi-college tenancy, or a background job system. Login throttling and automated end-to-end testing are also areas to consider before production use. The global room/seat uniqueness rule may need redesign if a physical seat should be reusable for different examinations.

Possible future enhancements include configurable seat-reuse policy, automated test coverage, login abuse controls, audit trails, notification integrations, and production deployment configuration.
