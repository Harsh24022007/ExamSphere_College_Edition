# ExamSphere College Edition — ER Diagram Notes

## Deliverables

- [Editable Mermaid ER diagram](examsphere_er_diagram.mmd)
- This device does not have Mermaid CLI or Graphviz installed, so no rasterized PNG was generated. The `.mmd` file is directly renderable in Mermaid-compatible editors and documentation tools.

## Scope and source basis

This model was derived from the supplied `db_setup.sql`, `auth.sql`, `cascade_delete_migration.sql`, `student_id_autoincrement_migration.sql`, `README.md`, `PROJECT_REPORT.md`, and the SQL queries and deletion logic in the PHP application. Existing application files were not modified.

The authoritative fresh-install schema contains these tables: `DEPARTMENT`, `SUBJECT`, `STUDENTS`, `EXAMINATION`, `ROOMS`, `INVIGILATOR`, `EXAM_REGISTRATION`, `ROOM_ALLOCATION`, `INVIGILATOR_DUTY`, `users`, and `ADMIN_USERS`.

## Relationship interpretation

| Relationship | Meaning | Constraint or behavior |
|---|---|---|
| `DEPARTMENT` → `SUBJECT` | A department offers subjects. | `SUBJECT.DEPT_ID` is mandatory FK. |
| `DEPARTMENT` → `STUDENTS` | Students belong to a department. | `STUDENTS.DEPT_ID` is mandatory FK. |
| `SUBJECT` → `EXAMINATION` | Exams are scheduled for subjects. | `EXAMINATION.SUBJECT_ID` is mandatory FK. |
| `STUDENTS` ↔ `EXAMINATION` | Students register for exams through the associative entity `EXAM_REGISTRATION`. | `UNIQUE(STUDENT_ID, EXAM_ID)` prevents duplicate registration. |
| `EXAM_REGISTRATION` → `ROOM_ALLOCATION` | A registration can receive no seat or one seat. | `ROOM_ALLOCATION.REGISTR_ID` is `UNIQUE`; deleting a registration cascades to its allocation. |
| `ROOMS` → `ROOM_ALLOCATION` | A room contains allocated seats. | `UNIQUE(ROOM_ID, SEAT_NO)` prevents duplicate room/seat pairs in the supplied schema. |
| `EXAMINATION` ↔ `INVIGILATOR` | Invigilators are assigned to exam rooms through `INVIGILATOR_DUTY`. | Duty also references `ROOMS`; `UNIQUE(EXAM_ID, INVIGILATOR_ID, ROOM_ID)` prevents duplicate duty rows. |
| `STUDENTS` ↔ `users` | A student may have one linked login account. | `users.student_id` is nullable and unique. |
| `INVIGILATOR` ↔ `users` | An invigilator may have one linked login account. | `users.invigilator_id` is nullable and unique. |
| `ADMIN_USERS` | Separate administrator authentication store used by `login.php` for the admin portal. | Standalone in the supplied schema; it is not linked by FK to `users`. |

## Cascades and deletion behavior

The supplied schema and migration establish these database cascades:

- Deleting an `EXAMINATION` cascades to its `EXAM_REGISTRATION` rows.
- Deleting an `EXAM_REGISTRATION` cascades to its `ROOM_ALLOCATION` row.
- Deleting an `EXAMINATION` cascades to its `INVIGILATOR_DUTY` rows.
- The migration also expects `attendance.allocation_id` to cascade from `ROOM_ALLOCATION.ALLOCATION_ID`.

The PHP management workflow additionally performs coordinated department, student, and invigilator cleanup in transactions. That application-level behavior is intentionally described in notes rather than represented as extra foreign keys that do not exist in the supplied schema.

## Attendance caveat

`attendance` is referenced by the cascade migration and by conditional PHP deletion/unlinking logic, but its complete `CREATE TABLE` definition is not present in the repository's fresh-install schema. The diagram therefore marks it as an **optional existing-deployment extension**. Only the following are supported by repository evidence:

- `attendance_id` is read by `manage.php`.
- `allocation_id` references `ROOM_ALLOCATION.ALLOCATION_ID` in `cascade_delete_migration.sql`.
- `checked_by` is nulled when a linked user is deleted; its link to `users.user_id` is inferred from the PHP logic but not declared in the supplied migration.
- Other attendance columns and exact nullability/cardinality are not defined in the supplied files.

No undocumented attendance columns were invented as part of the canonical fresh-install model.

## Integrity rules captured

- Primary keys are shown for every supplied table.
- `STUDENTS.STUDENT_ID` and the generated IDs in `users` / `ADMIN_USERS` are marked `AUTO_INCREMENT` where defined by the scripts.
- Nullable fields are identified where the schema permits them.
- Role values in `users.role` are constrained to `admin`, `student`, or `invigilator`.
- `users.student_id` and `users.invigilator_id` are nullable unique foreign keys, allowing administrator accounts with neither profile link.
- `EXAMINATION.published` defaults to `1`.
- `ROOMS.STATUS` defaults to `AVAILABLE`.
