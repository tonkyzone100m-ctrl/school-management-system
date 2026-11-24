<?php

require __DIR__ . "/functions.php";
require __DIR__ . "/db.php";

require_login("hod");

$message = "";
$messageType = "info";

/* Add a module */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_module"])) {
    csrf_verify();

    $moduleName = post("module_name");
    $moduleCode = post("module_code");

    if ($moduleName === "" || $moduleCode === "") {
        $message = "Module name and module code are required.";
        $messageType = "error";
    } elseif (strlen($moduleName) > 100) {
        $message = "Module name must be 100 characters or fewer.";
        $messageType = "error";
    } elseif (strlen($moduleCode) > 50) {
        $message = "Module code must be 50 characters or fewer.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT id FROM modules WHERE module_code = ? LIMIT 1");
        $check->bind_param("s", $moduleCode);
        $check->execute();
        $codeTaken = $check->get_result()->num_rows > 0;
        $check->close();

        if ($codeTaken) {
            $message = "A module with this code already exists.";
            $messageType = "error";
        } else {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO modules (module_name, module_code) VALUES (?, ?)"
                );
                $stmt->bind_param("ss", $moduleName, $moduleCode);
                $stmt->execute();
                $stmt->close();

                $message = "Module added successfully.";
                $messageType = "success";
            } catch (mysqli_sql_exception $e) {
                error_log("HOD add module failed: " . $e->getMessage());
                $message = "Could not add the module. The module code may already be in use.";
                $messageType = "error";
            }
        }
    }
}

/* Assign a module to a lecturer */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["assign"])) {
    csrf_verify();

    $lecturerId = filter_input(INPUT_POST, "lecturer", FILTER_VALIDATE_INT);
    $moduleId = filter_input(INPUT_POST, "module", FILTER_VALIDATE_INT);

    if ($lecturerId === false || $lecturerId === null || $moduleId === false || $moduleId === null) {
        $message = "Please choose both a lecturer and a module.";
        $messageType = "error";
    } else {
        $lecturerCheck = $conn->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'lecturer' LIMIT 1"
        );
        $lecturerCheck->bind_param("i", $lecturerId);
        $lecturerCheck->execute();
        $lecturerOk = $lecturerCheck->get_result()->num_rows > 0;
        $lecturerCheck->close();

        $moduleCheck = $conn->prepare("SELECT id FROM modules WHERE id = ? LIMIT 1");
        $moduleCheck->bind_param("i", $moduleId);
        $moduleCheck->execute();
        $moduleOk = $moduleCheck->get_result()->num_rows > 0;
        $moduleCheck->close();

        if (!$lecturerOk) {
            $message = "The selected lecturer could not be found.";
            $messageType = "error";
        } elseif (!$moduleOk) {
            $message = "The selected module could not be found.";
            $messageType = "error";
        } else {
            $dup = $conn->prepare(
                "SELECT id FROM module_assignments WHERE lecturer_id = ? AND module_id = ? LIMIT 1"
            );
            $dup->bind_param("ii", $lecturerId, $moduleId);
            $dup->execute();
            $alreadyAssigned = $dup->get_result()->num_rows > 0;
            $dup->close();

            if ($alreadyAssigned) {
                $message = "That module is already assigned to this lecturer.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO module_assignments (lecturer_id, module_id) VALUES (?, ?)"
                    );
                    $stmt->bind_param("ii", $lecturerId, $moduleId);
                    $stmt->execute();
                    $stmt->close();

                    $message = "Module assigned successfully.";
                    $messageType = "success";
                } catch (mysqli_sql_exception $e) {
                    error_log("HOD assign module failed: " . $e->getMessage());
                    $message = "Could not assign the module. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* Remove a lecturer-module assignment */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["unassign"])) {
    csrf_verify();

    $assignmentId = filter_input(INPUT_POST, "assignment_id", FILTER_VALIDATE_INT);

    if ($assignmentId === false || $assignmentId === null) {
        $message = "Invalid assignment selected.";
        $messageType = "error";
    } else {
        $del = $conn->prepare("DELETE FROM module_assignments WHERE id = ?");
        $del->bind_param("i", $assignmentId);
        $del->execute();
        $removed = $del->affected_rows > 0;
        $del->close();

        if ($removed) {
            $message = "Assignment removed.";
            $messageType = "success";
        } else {
            $message = "That assignment could not be found.";
            $messageType = "error";
        }
    }
}

/* Delete a module (and its assignments/marks via cascade) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_module"])) {
    csrf_verify();

    $moduleId = filter_input(INPUT_POST, "module_id", FILTER_VALIDATE_INT);

    if ($moduleId === false || $moduleId === null) {
        $message = "Invalid module selected.";
        $messageType = "error";
    } else {
        $del = $conn->prepare("DELETE FROM modules WHERE id = ?");
        $del->bind_param("i", $moduleId);
        $del->execute();
        $deleted = $del->affected_rows > 0;
        $del->close();

        if ($deleted) {
            $message = "Module deleted.";
            $messageType = "success";
        } else {
            $message = "That module could not be found.";
            $messageType = "error";
        }
    }
}

/* Lecturers for the assignment dropdown */
$lecturers = [];
$lecturerList = $conn->prepare(
    "SELECT id, full_name FROM users WHERE role = 'lecturer' ORDER BY full_name"
);
$lecturerList->execute();
$lecturerResult = $lecturerList->get_result();
while ($row = $lecturerResult->fetch_assoc()) {
    $lecturers[] = $row;
}
$lecturerList->close();

/* Modules with assignment counts */
$modules = [];
$moduleList = $conn->prepare(
    "SELECT m.id, m.module_name, m.module_code, COUNT(ma.id) AS assignment_count
     FROM modules m
     LEFT JOIN module_assignments ma ON ma.module_id = m.id
     GROUP BY m.id, m.module_name, m.module_code
     ORDER BY m.module_name"
);
$moduleList->execute();
$moduleResult = $moduleList->get_result();
while ($row = $moduleResult->fetch_assoc()) {
    $modules[] = $row;
}
$moduleList->close();

/* Current assignments */
$assignments = [];
$assignmentList = $conn->prepare(
    "SELECT ma.id, u.full_name AS lecturer_name, m.module_name, m.module_code
     FROM module_assignments ma
     INNER JOIN users u ON u.id = ma.lecturer_id
     INNER JOIN modules m ON m.id = ma.module_id
     ORDER BY u.full_name, m.module_name"
);
$assignmentList->execute();
$assignmentResult = $assignmentList->get_result();
while ($row = $assignmentResult->fetch_assoc()) {
    $assignments[] = $row;
}
$assignmentList->close();

page_header("HOD Dashboard", [
    "name" => $_SESSION["name"],
    "role" => $_SESSION["role"],
    "picture" => $_SESSION["picture"] ?? "",
]);
?>

<h2>HOD Dashboard</h2>
<p class="muted">Welcome, <?= h($_SESSION["name"]) ?>.</p>

<?php alert($message, $messageType); ?>

<div class="grid">
    <section class="card">
        <h3>Add Module</h3>

        <form method="POST" action="hod.php">
            <?= csrf_field() ?>

            <div class="field">
                <label for="module_name">Module Name</label>
                <input type="text" id="module_name" name="module_name" maxlength="100" required>
            </div>

            <div class="field">
                <label for="module_code">Module Code</label>
                <input type="text" id="module_code" name="module_code" maxlength="50" required>
            </div>

            <button type="submit" name="add_module" class="btn">Add Module</button>
        </form>
    </section>

    <section class="card">
        <h3>Assign Module to Lecturer</h3>

        <?php if ($lecturers === [] || $modules === []): ?>
            <p class="muted">Add at least one lecturer and one module before assigning.</p>
        <?php else: ?>
            <form method="POST" action="hod.php">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="lecturer">Lecturer</label>
                    <select id="lecturer" name="lecturer" required>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?= h($lecturer["id"]) ?>"><?= h($lecturer["full_name"]) ?></option>
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

                <button type="submit" name="assign" class="btn">Assign Module</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <h3>Modules <span class="badge badge-neutral"><?= h(count($modules)) ?></span></h3>

    <?php if ($modules === []): ?>
        <p class="empty-state">No modules have been created yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Module</th>
                        <th scope="col">Code</th>
                        <th scope="col">Assignments</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                        <tr>
                            <td><?= h($module["module_name"]) ?></td>
                            <td><?= h($module["module_code"]) ?></td>
                            <td><span class="badge badge-neutral"><?= h($module["assignment_count"]) ?></span></td>
                            <td>
                                <form method="POST" action="hod.php" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="module_id" value="<?= h($module["id"]) ?>">
                                    <button type="submit" name="delete_module" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Current Assignments <span class="badge badge-neutral"><?= h(count($assignments)) ?></span></h3>

    <?php if ($assignments === []): ?>
        <p class="empty-state">No modules are currently assigned to lecturers.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Lecturer</th>
                        <th scope="col">Module</th>
                        <th scope="col">Code</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td><?= h($assignment["lecturer_name"]) ?></td>
                            <td><?= h($assignment["module_name"]) ?></td>
                            <td><?= h($assignment["module_code"]) ?></td>
                            <td>
                                <form method="POST" action="hod.php" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="assignment_id" value="<?= h($assignment["id"]) ?>">
                                    <button type="submit" name="unassign" class="btn btn-small btn-danger">Unassign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php page_footer(); ?>
