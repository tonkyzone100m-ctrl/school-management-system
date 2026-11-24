<?php

require __DIR__ . "/functions.php";
require __DIR__ . "/db.php";

if (!empty($_SESSION["id"]) && !empty($_SESSION["role"])) {
    redirect(dashboard_for($_SESSION["role"]));
}

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    [$blocked, $retryAfter] = login_rate_limited();

    if ($blocked) {
        $minutes = (int) ceil($retryAfter / 60);
        $message = "Too many failed login attempts. Please try again in about "
            . max(1, $minutes) . " minute(s).";
    } else {
        $email = post("email");
        $password = isset($_POST["password"]) && is_string($_POST["password"])
            ? $_POST["password"]
            : "";

        if ($email === "" || $password === "") {
            $message = "Please enter both your email and password.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare(
                "SELECT id, full_name, email, password, role, profile_picture
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user !== null && password_verify($password, $user["password"])) {
                if (password_needs_rehash($user["password"], PASSWORD_DEFAULT)) {
                    $rehash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->bind_param("si", $rehash, $user["id"]);
                    $update->execute();
                    $update->close();
                }

                login_clear_failures();
                session_regenerate_id(true);

                $_SESSION["id"] = (int) $user["id"];
                $_SESSION["name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["picture"] = $user["profile_picture"];

                redirect(dashboard_for($user["role"]));
            }

            login_record_failure();
            $message = "Invalid email or password.";
        }
    }
}

page_header("Login");
?>

<section class="card card-narrow">
    <h2>School Management System</h2>
    <h3>Sign in</h3>

    <?php alert($message, $messageType); ?>

    <form method="POST" action="login.php" autocomplete="on">
        <?= csrf_field() ?>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn">Login</button>
    </form>
</section>

<?php page_footer(); ?>
