<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();

/*
 * This page is intentionally read-only. Every statement is a fixed SELECT
 * query against the existing ExamSphere schema; no user input is interpolated.
 */
$labQueries = [
    "Right Join" => [
        "description" => "Shows every room, including rooms without a seat allocation.",
        "sql" => "SELECT r.ROOM_ID, r.BUILDING, ra.ALLOCATION_ID, ra.SEAT_NO
                  FROM ROOM_ALLOCATION ra
                  RIGHT JOIN ROOMS r ON r.ROOM_ID = ra.ROOM_ID
                  ORDER BY r.ROOM_ID, ra.SEAT_NO",
    ],
    "Full Outer Join (MySQL emulation)" => [
        "description" => "Combines all departments and students, including unmatched records on either side.",
        "sql" => "SELECT d.DEPT_ID, d.DEPT_NAME, st.STUDENT_ID,
                         CONCAT_WS(' ', st.FNAME, st.MNAME, st.LNAME) AS STUDENT_NAME
                  FROM DEPARTMENT d
                  LEFT JOIN STUDENTS st ON st.DEPT_ID = d.DEPT_ID
                  UNION ALL
                  SELECT d.DEPT_ID, d.DEPT_NAME, st.STUDENT_ID,
                         CONCAT_WS(' ', st.FNAME, st.MNAME, st.LNAME) AS STUDENT_NAME
                  FROM STUDENTS st
                  LEFT JOIN DEPARTMENT d ON d.DEPT_ID = st.DEPT_ID
                  WHERE d.DEPT_ID IS NULL
                  ORDER BY DEPT_ID, STUDENT_ID",
    ],
    "Cross Join" => [
        "description" => "Creates department/room combinations for planning coverage; output is capped at 20 rows.",
        "sql" => "SELECT d.DEPT_NAME, r.ROOM_ID, r.BUILDING
                  FROM DEPARTMENT d
                  CROSS JOIN ROOMS r
                  ORDER BY d.DEPT_ID, r.ROOM_ID
                  LIMIT 20",
    ],
    "Self Join" => [
        "description" => "Finds distinct pairs of rooms located in the same building.",
        "sql" => "SELECT r1.BUILDING, r1.ROOM_ID AS ROOM_ONE,
                         r2.ROOM_ID AS ROOM_TWO,
                         r1.CAPACITY AS ROOM_ONE_CAPACITY,
                         r2.CAPACITY AS ROOM_TWO_CAPACITY
                  FROM ROOMS r1
                  INNER JOIN ROOMS r2
                    ON r1.BUILDING = r2.BUILDING
                   AND r1.ROOM_ID < r2.ROOM_ID
                  ORDER BY r1.BUILDING, r1.ROOM_ID, r2.ROOM_ID",
    ],
    "AVG / MIN / MAX with HAVING" => [
        "description" => "Summarizes rooms by building and keeps buildings whose average capacity is at least 20.",
        "sql" => "SELECT BUILDING, COUNT(*) AS ROOM_COUNT,
                         AVG(CAPACITY) AS AVERAGE_CAPACITY,
                         MIN(CAPACITY) AS MINIMUM_CAPACITY,
                         MAX(CAPACITY) AS MAXIMUM_CAPACITY
                  FROM ROOMS
                  GROUP BY BUILDING
                  HAVING AVG(CAPACITY) >= 20
                  ORDER BY AVERAGE_CAPACITY DESC, BUILDING",
    ],
    "Joined Aggregate Summary" => [
        "description" => "Combines COUNT, AVG, MIN, MAX, GROUP BY, HAVING, and multiple joins by department.",
        "sql" => "SELECT d.DEPT_ID, d.DEPT_NAME,
                         COUNT(DISTINCT st.STUDENT_ID) AS STUDENT_COUNT,
                         COUNT(DISTINCT er.REGISTR_ID) AS REGISTRATION_COUNT,
                         AVG(r.CAPACITY) AS AVERAGE_ROOM_CAPACITY,
                         MIN(r.CAPACITY) AS SMALLEST_ASSIGNED_ROOM,
                         MAX(r.CAPACITY) AS LARGEST_ASSIGNED_ROOM
                  FROM DEPARTMENT d
                  LEFT JOIN STUDENTS st ON st.DEPT_ID = d.DEPT_ID
                  LEFT JOIN EXAM_REGISTRATION er ON er.STUDENT_ID = st.STUDENT_ID
                  LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID = er.REGISTR_ID
                  LEFT JOIN ROOMS r ON r.ROOM_ID = ra.ROOM_ID
                  GROUP BY d.DEPT_ID, d.DEPT_NAME
                  HAVING COUNT(DISTINCT st.STUDENT_ID) > 0
                  ORDER BY STUDENT_COUNT DESC, d.DEPT_ID",
    ],
];

$results = [];
foreach ($labQueries as $title => $definition) {
    try {
        $statement = $pdo->query($definition["sql"]);
        $rows = $statement->fetchAll();
        $columns = $rows ? array_keys($rows[0]) : [];
        $results[$title] = [
            "description" => $definition["description"],
            "sql" => preg_replace('/\s+/', ' ', trim($definition["sql"])),
            "rows" => $rows,
            "columns" => $columns,
            "error" => null,
        ];
    } catch (Throwable $ex) {
        // Keep one unsupported/empty optional example from breaking the page.
        $results[$title] = [
            "description" => $definition["description"],
            "sql" => preg_replace('/\s+/', ' ', trim($definition["sql"])),
            "rows" => [],
            "columns" => [],
            "error" => $ex->getMessage(),
        ];
    }
}

include "partials.php";
?>
<main class="content">
  <div class="content-title">
    <div>
      <span class="eyebrow">DATABASE LAB</span>
      <h1>SQL Joins &amp; Aggregates</h1>
      <p>Live, read-only examples using the ExamSphere database. No records are changed.</p>
    </div>
    <div class="title-actions"><a class="btn ghost" href="dashboard.php">← Dashboard</a></div>
  </div>

  <section class="panel">
    <div class="panel-head"><div><h2>Coverage</h2><p>All examples run when this page loads using the current database contents.</p></div><span class="badge">SELECT only</span></div>
    <div class="dash-cards" style="margin:0">
      <div class="dash-card cyan"><span>RIGHT JOIN</span><b>Live</b><small>Rooms and allocations</small></div>
      <div class="dash-card blue"><span>FULL OUTER</span><b>Live</b><small>UNION-based MySQL emulation</small></div>
      <div class="dash-card violet"><span>CROSS / SELF</span><b>Live</b><small>Planning and room comparisons</small></div>
      <div class="dash-card green"><span>AGGREGATES</span><b>Live</b><small>AVG, MIN, MAX, HAVING</small></div>
    </div>
  </section>

  <?php foreach ($results as $title => $result): ?>
    <section class="panel">
      <div class="panel-head">
        <div><h2><?= e($title) ?></h2><p><?= e($result["description"]) ?></p></div>
        <span class="badge"><?= $result["error"] ? "Unavailable" : count($result["rows"]) . " row(s)" ?></span>
      </div>
      <details>
        <summary class="link" style="cursor:pointer;font-size:12px;margin-bottom:12px">View SQL query</summary>
        <pre style="white-space:pre-wrap;overflow:auto;background:#081626;border:1px solid var(--line);border-radius:9px;padding:14px;color:#b8d5ee;font-size:11px;line-height:1.6"><?= e($result["sql"]) ?></pre>
      </details>
      <?php if ($result["error"]): ?>
        <div class="alert danger">This example could not be executed: <?= e($result["error"]) ?></div>
      <?php elseif (!$result["rows"]): ?>
        <div class="empty">The query ran successfully but returned no rows for the current data.</div>
      <?php else: ?>
        <div class="table-wrap"><table><thead><tr><?php foreach ($result["columns"] as $column): ?><th><?= e(str_replace("_", " ", $column)) ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ($result["rows"] as $row): ?><tr><?php foreach ($result["columns"] as $column): ?><td><?= e($row[$column] ?? "—") ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</main>
<?php include "end.php"; ?>
