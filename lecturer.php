<?php

require __DIR__ . "/functions.php";
require __DIR__ . "/db.php";

require_login("lecturer");

$lecturerId = (int) $_SESSION["id"];
$message = "";
$messageType = "info";

/**
 * Confirm a mark belongs to a module assigned to this lecturer.
 */
function lecturer_owns_mark(mysqli $conn, int $markId, int $lecturerId): bool
{
    $stmt = $conn->prepare(
        "SELECT mk.id
         FROM marks mk
         INNER JOIN module_assignments ma ON ma.module_id = mk.module_id
         WHERE mk.id = ? AND ma.lecturer_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $markId, $lecturerId);
    $stmt->execute();
    $owned = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $owned;
}

/* Add a mark */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_mark"])) {
    csrf_verify();

    $studentId = filter_input(INPUT_POST, "student", FILTER_VALIDATE_INT);
    $moduleId = filter_input(INPUT_POST, "module", FILTER_VALIDATE_INT);
    $mark = filter_var(post("mark"), FILTER_VALIDATE_FLOAT);

    if ($studentId === false || $studentId === null || $moduleId === false || $moduleId === null) {
        $message = "Please choose both a student and a module.";
        $messageType = "error";
    } elseif ($mark === false) {
        $message = "Please enter a valid numeric mark.";
        $messageType = "error";
    } elseif ($mark < 0 || $mark > 100) {
        $message = "The mark must be between 0 and 100.";
        $messageType = "error";
    } else {
        $studentCheck = $conn->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1"
        );
        $studentCheck->bind_param("i", $studentId);
        $studentCheck->execute();
        $studentOk = $studentCheck->get_result()->num_rows > 0;
        $studentCheck->close();

        $assignCheck = $conn->prepare(
            "SELECT id FROM module_assignments WHERE lecturer_id = ? AND module_id = ? LIMIT 1"
        );
        $assignCheck->bind_param("ii", $lecturerId, $moduleId);
        $assignCheck->execute();
        $assignedOk = $assignCheck->get_result()->num_rows > 0;
        $assignCheck->close();

        if (!$studentOk) {
            $message = "The selected student could not be found.";
            $messageType = "error";
        } elseif (!$assignedOk) {
            $message = "You can only enter marks for modules assigned to you.";
            $messageType = "error";
        } else {
            $dup = $conn->prepare(
                "SELECT id FROM marks WHERE student_id = ? AND module_id = ? LIMIT 1"
            );
            $dup->bind_param("ii", $studentId, $moduleId);
            $dup->execute();
            $markExists = $dup->get_result()->num_rows > 0;
            $dup->close();

            if ($markExists) {
                $message = "A mark for this student and module already exists.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO marks (student_id, module_id, marks) VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("iid", $studentId, $moduleId, $mark);
                    $stmt->execute();
                    $stmt->close();

                    $message = "Mark added successfully.";
                    $messageType = "success";
                } catch (mysqli_sql_exception $e) {
                    error_log("Lecturer add mark failed: " . $e->getMessage());
                    $message = "Could not add the mark. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* Update an existing mark */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_mark"])) {
    csrf_verify();

    $markId = filter_input(INPUT_POST, "mark_id", FILTER_VALIDATE_INT);
    $mark = filter_var(post("mark"), FILTER_VALIDATE_FLOAT);

    if ($markId === false || $markId === null) {
        $message = "Invalid mark selected.";
        $messageType = "error";
    } elseif ($mark === false) {
        $message = "Please enter a valid numeric mark.";
        $messageType = "error";
    } elseif ($mark < 0 || $mark > 100) {
        $message = "The mark must be between 0 and 100.";
        $messageType = "error";
    } elseif (!lecturer_owns_mark($conn, $markId, $lecturerId)) {
        $message = "You can only edit marks for modules assigned to you.";
        $messageType = "error";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE marks SET marks = ? WHERE id = ?");
            $stmt->bind_param("di", $mark, $markId);
            $stmt->execute();
            $stmt->close();

            $message = "Mark updated.";
            $messageType = "success";
        } catch (mysqli_sql_exception $e) {
            error_log("Lecturer update mark failed: " . $e->getMessage());
            $message = "Could not update the mark. Please try again.";
            $messageType = "error";
        }
    }
}

/* Delete a mark */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_mark"])) {
    csrf_verify();

    $markId = filter_input(INPUT_POST, "mark_id", FILTER_VALIDATE_INT);

    if ($markId === false || $markId === null) {
        $message = "Invalid mark selected.";
        $messageType = "error";
    } elseif (!lecturer_owns_mark($conn, $markId, $lecturerId)) {
        $message = "You can only delete marks for modules assigned to you.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM marks WHERE id = ?");
        $stmt->bind_param("i", $markId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if ($deleted) {
            $message = "Mark deleted.";
            $messageType = "success";
        } else {
            $message = "That mark could not be found.";
            $messageType = "error";
        }
    }
}

/* Students for the add form */
$students = [];
$studentList = $conn->prepare(
    "SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name"
);
$studentList->execute();
$studentResult = $studentList->get_result();
while ($row = $studentResult->fetch_assoc()) {
    $students[] = $row;
}
$studentList->close();

/* Modules assigned to this lecturer */
$modules = [];
$moduleList = $conn->prepare(
    "SELECT modules.id, modules.module_name, modules.module_code
     FROM modules
     INNER JOIN module_assignments ON modules.id = module_assignments.module_id
     WHERE module_assignments.lecturer_id = ?
     ORDER BY modules.module_name"
);
$moduleList->bind_param("i", $lecturerId);
$moduleList->execute();
$moduleResult = $moduleList->get_result();
while ($row = $moduleResult->fetch_assoc()) {
    $modules[] = $row;
}
$moduleList->close();

/* Marks recorded for this lecturer's modules */
$marks = [];
$markList = $conn->prepare(
    "SELECT mk.id, mk.marks, u.full_name AS student_name, m.module_name, m.module_code
     FROM marks mk
     INNER JOIN users u ON u.id = mk.student_id
     INNER JOIN modules m ON m.id = mk.module_id
     INNER JOIN module_assignments ma ON ma.module_id = mk.module_id AND ma.lecturer_id = ?
     ORDER BY m.module_name, u.full_name"
);
$markList->bind_param("i", $lecturerId);
$markList->execute();
$markResult = $markList->get_result();
while ($row = $markResult->fetch_assoc()) {
    $marks[] = $row;
}
$markList->close();

page_header("Lecturer Dashboard", [
    "name" => $_SESSION["name"],
    "role" => $_SESSION["role"],
    "picture" => $_SESSION["picture"] ?? "",
]);
?>

<h2>Lecturer Dashboard</h2>
<p class="muted">Welcome, <?= h($_SESSION["name"]) ?>.</p>

<?php alert($message, $messageType); ?>

<section class="card">
    <h3>Add Student Marks</h3>

    <?php if ($students === [] || $modules === []): ?>
        <p class="muted">
            You need at least one registered student and at least one module assigned to you before
            you can add marks.
        </p>
    <?php else: ?>
        <form method="POST" action="lecturer.php">
            <?= csrf_field() ?>

            <div class="field">
                <label for="student">Student</label>
                <select id="student" name="student" required>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= h($student["id"]) ?>"><?= h($student["full_name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="module">Module</label>
                <select id="module" name="module" required>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= h($module["id"]) ?>">
                            <?= h($module["module_name"]) ?> (<?= h($module["module_code"]) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="mark">Mark</label>
                <input type="number" id="mark" name="mark" min="0" max="100" step="0.01" required>
                <span class="hint">Enter a value between 0 and 100.</span>
            </div>

            <button type="submit" name="add_mark" class="btn">Add Mark</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Recorded Marks <span class="badge badge-neutral"><?= h(count($marks)) ?></span></h3>

    <?php if ($marks === []): ?>
        <p class="empty-state">No marks have been recorded for your modules yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Module</th>
                        <th scope="col">Mark</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marks as $record): ?>
                        <tr>
                            <td><?= h($record["student_name"]) ?></td>
                            <td>
                                <?= h($record["module_name"]) ?>
                                <span class="muted">(<?= h($record["module_code"]) ?>)</span>
                            </td>
                            <td><?= h($record["marks"]) ?></td>
                            <td>
                                <div class="table-actions">
                                    <form method="POST" action="lecturer.php" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mark_id" value="<?= h($record["id"]) ?>">
                                        <input
                                            type="number"
                                            name="mark"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value="<?= h($record["marks"]) ?>"
                                            required
                                            aria-label="New mark for <?= h($record["student_name"]) ?>"
                                        >
                                        <button type="submit" name="update_mark" class="btn btn-small">Update</button>
                                    </form>

                                    <form method="POST" action="lecturer.php" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mark_id" value="<?= h($record["id"]) ?>">
                                        <button type="submit" name="delete_mark" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php page_footer(); ?>
