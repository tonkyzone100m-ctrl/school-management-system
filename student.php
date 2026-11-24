<?php

require __DIR__ . "/functions.php";
require __DIR__ . "/db.php";

require_login("student");

$studentId = (int) $_SESSION["id"];

$stmt = $conn->prepare(
    "SELECT modules.module_name, modules.module_code, marks.marks
     FROM marks
     INNER JOIN modules ON marks.module_id = modules.id
     WHERE marks.student_id = ?
     ORDER BY modules.module_name"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$total = 0.0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total += (float) $row["marks"];
}
$stmt->close();

$moduleCount = count($rows);
$average = $moduleCount > 0 ? $total / $moduleCount : null;
$passThreshold = 50.0;
$status = "No marks";
$statusClass = "badge-neutral";

if ($average !== null) {
    if ($average >= $passThreshold) {
        $status = "Pass";
        $statusClass = "badge-pass";
    } else {
        $status = "Fail";
        $statusClass = "badge-fail";
    }
}

page_header("Student Dashboard", [
    "name" => $_SESSION["name"],
    "role" => $_SESSION["role"],
    "picture" => $_SESSION["picture"] ?? "",
]);
?>

<h2>Student Dashboard</h2>
<p class="muted">Welcome, <?= h($_SESSION["name"]) ?>.</p>

<?php if ($rows === []): ?>
    <section class="card">
        <h3>My Marks</h3>
        <p class="empty-state">No marks have been recorded for you yet.</p>
    </section>
<?php else: ?>
    <div class="summary-grid">
        <div class="stat">
            <div class="stat-label">Modules</div>
            <div class="stat-value"><?= h($moduleCount) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Average Mark</div>
            <div class="stat-value"><?= h(number_format($average, 2)) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Status</div>
            <div class="stat-value">
                <span class="badge <?= h($statusClass) ?>"><?= h($status) ?></span>
            </div>
        </div>
    </div>

    <section class="card">
        <h3>My Marks</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Module</th>
                        <th scope="col">Module Code</th>
                        <th scope="col">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row["module_name"]) ?></td>
                            <td><?= h($row["module_code"]) ?></td>
                            <td><?= h($row["marks"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="hint">
            Pass mark is <?= h(number_format($passThreshold, 0)) ?>. Status is based on your average mark.
        </p>
    </section>
<?php endif; ?>

<?php page_footer(); ?>
