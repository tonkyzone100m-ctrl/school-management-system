<?php

require __DIR__ . "/functions.php";
require __DIR__ . "/db.php";

require_login("hr");

$message = "";
$messageType = "info";

/* Add a lecturer */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add"])) {
    csrf_verify();

    $name = post("name");
    $email = post("email");
    $password = isset($_POST["password"]) && is_string($_POST["password"]) ? $_POST["password"] : "";

    if ($name === "" || $email === "" || $password === "") {
        $message = "Full name, email, and password are all required.";
        $messageType = "error";
    } elseif (strlen($name) > 100) {
        $message = "Full name must be 100 characters or fewer.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = "Please enter a valid email address (up to 100 characters).";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $emailTaken = $check->get_result()->num_rows > 0;
        $check->close();

        if ($emailTaken) {
            $message = "An account with this email already exists.";
            $messageType = "error";
        } else {
            $uploadError = null;
            $picture = save_profile_picture("picture", $uploadError);

            if ($uploadError !== null) {
                $message = $uploadError;
                $messageType = "error";
            } else {
                $picture = $picture ?? "";
                $hash = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO users (full_name, email, password, role, profile_picture)
                         VALUES (?, ?, ?, 'lecturer', ?)"
                    );
                    $stmt->bind_param("ssss", $name, $email, $hash, $picture);
                    $stmt->execute();
                    $stmt->close();

                    $message = "Lecturer added successfully.";
                    $messageType = "success";
                } catch (mysqli_sql_exception $e) {
                    error_log("HR add lecturer failed: " . $e->getMessage());
                    $message = "Could not add the lecturer. The email may already be in use.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* Delete a lecturer */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete"])) {
    csrf_verify();

    $lecturerId = filter_input(INPUT_POST, "lecturer_id", FILTER_VALIDATE_INT);

    if ($lecturerId === false || $lecturerId === null) {
        $message = "Invalid lecturer selected.";
        $messageType = "error";
    } else {
        $find = $conn->prepare(
            "SELECT id, profile_picture FROM users WHERE id = ? AND role = 'lecturer' LIMIT 1"
        );
        $find->bind_param("i", $lecturerId);
        $find->execute();
        $lecturer = $find->get_result()->fetch_assoc();
        $find->close();

        if ($lecturer === null) {
            $message = "That lecturer could not be found.";
            $messageType = "error";
        } else {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'lecturer'");
            $del->bind_param("i", $lecturerId);
            $del->execute();
            $deleted = $del->affected_rows > 0;
            $del->close();

            if ($deleted) {
                delete_upload($lecturer["profile_picture"]);
                $message = "Lecturer deleted.";
                $messageType = "success";
            } else {
                $message = "Could not delete the lecturer.";
                $messageType = "error";
            }
        }
    }
}

/* Load lecturers for display */
$lecturers = [];
$list = $conn->prepare(
    "SELECT id, full_name, email, profile_picture FROM users WHERE role = 'lecturer' ORDER BY full_name"
);
$list->execute();
$result = $list->get_result();
while ($row = $result->fetch_assoc()) {
    $lecturers[] = $row;
}
$list->close();

page_header("HR Dashboard", [
    "name" => $_SESSION["name"],
    "role" => $_SESSION["role"],
    "picture" => $_SESSION["picture"] ?? "",
]);
?>

<h2>HR Dashboard</h2>
<p class="muted">Welcome, <?= h($_SESSION["name"]) ?>.</p>

<?php alert($message, $messageType); ?>

<section class="card">
    <h3>Add Lecturer</h3>

    <form method="POST" action="hr.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" maxlength="100" required>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="100" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="6" required>
            <span class="hint">At least 6 characters.</span>
        </div>

        <div class="field">
            <label for="picture">Profile Picture</label>
            <input type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/gif,image/webp">
            <span class="hint">JPG, PNG, GIF, or WEBP. Maximum 2 MB.</span>
        </div>

        <button type="submit" name="add" class="btn">Add Lecturer</button>
    </form>
</section>

<section class="card">
    <h3>Lecturers <span class="badge badge-neutral"><?= h(count($lecturers)) ?></span></h3>

    <?php if ($lecturers === []): ?>
        <p class="empty-state">No lecturers have been added yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Photo</th>
                        <th scope="col">Full Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lecturers as $lecturer): ?>
                        <?php $avatar = avatar_url($lecturer["profile_picture"]); ?>
                        <tr>
                            <td>
                                <?php if ($avatar !== null): ?>
                                    <img class="thumb" src="<?= h($avatar) ?>" alt="Photo of <?= h($lecturer["full_name"]) ?>">
                                <?php else: ?>
                                    <span class="muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($lecturer["full_name"]) ?></td>
                            <td><?= h($lecturer["email"]) ?></td>
                            <td>
                                <form method="POST" action="hr.php" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="lecturer_id" value="<?= h($lecturer["id"]) ?>">
                                    <button type="submit" name="delete" class="btn btn-small btn-danger">Delete</button>
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
