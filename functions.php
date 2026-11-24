<?php

/*
 * Shared helpers: sessions, security headers, CSRF, escaping, authorization,
 * login throttling, secure uploads, and UI layout.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
}

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: same-origin");
}

/**
 * Escape a value for safe HTML output.
 */
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

/**
 * Return a trimmed string value from $_POST, or a default.
 */
function post(string $key, string $default = ""): string
{
    if (isset($_POST[$key]) && is_string($_POST[$key])) {
        return trim($_POST[$key]);
    }

    return $default;
}

/**
 * Redirect and stop execution.
 */
function redirect(string $path): void
{
    header("Location: " . $path);
    exit();
}

/**
 * Map a user role to its dashboard URL.
 */
function dashboard_for(string $role): string
{
    $map = [
        "hr" => "hr.php",
        "registrar" => "registrar.php",
        "hod" => "hod.php",
        "lecturer" => "lecturer.php",
        "student" => "student.php",
    ];

    return $map[$role] ?? "login.php";
}

/**
 * Require an authenticated user, optionally with a specific role.
 */
function require_login(?string $role = null): void
{
    if (empty($_SESSION["id"]) || empty($_SESSION["role"])) {
        redirect("login.php");
    }

    if ($role !== null && $_SESSION["role"] !== $role) {
        redirect(dashboard_for($_SESSION["role"]));
    }
}

/**
 * Get (or create) the per-session CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Render a hidden CSRF field for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * Reject POST requests that do not carry a valid CSRF token.
 */
function csrf_verify(): void
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return;
    }

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        die("Invalid or expired form submission. Please go back and try again.");
    }
}

/**
 * Render a status alert. Empty messages render nothing.
 */
function alert(string $message, string $type = "info"): void
{
    if ($message === "") {
        return;
    }

    echo '<div class="alert alert-' . h($type) . '" role="status">' . h($message) . "</div>";
}

/**
 * Best-effort client IP address.
 */
function client_ip(): string
{
    return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
}

/**
 * Path of the per-IP login throttle file (outside the web root).
 */
function login_throttle_file(): string
{
    return sys_get_temp_dir() . "/sms_login_" . hash("sha256", client_ip()) . ".json";
}

/**
 * Read recent failed-login timestamps for this client.
 */
function login_throttle_read(): array
{
    $file = login_throttle_file();

    if (!is_file($file)) {
        return [];
    }

    $data = json_decode((string) @file_get_contents($file), true);

    if (!is_array($data)) {
        return [];
    }

    return array_values(array_filter($data, "is_int"));
}

/**
 * Return [blocked, seconds_remaining] for the current client.
 */
function login_rate_limited(): array
{
    $window = 900;
    $maxAttempts = 8;
    $now = time();

    $attempts = array_values(array_filter(
        login_throttle_read(),
        static fn (int $t): bool => $t > $now - $window
    ));

    if (count($attempts) >= $maxAttempts) {
        sort($attempts);
        $retry = ($attempts[0] + $window) - $now;

        return [true, max(1, $retry)];
    }

    return [false, 0];
}

/**
 * Record one failed login attempt for this client.
 */
function login_record_failure(): void
{
    $window = 900;
    $now = time();

    $attempts = array_values(array_filter(
        login_throttle_read(),
        static fn (int $t): bool => $t > $now - $window
    ));
    $attempts[] = $now;

    @file_put_contents(login_throttle_file(), json_encode($attempts), LOCK_EX);
}

/**
 * Clear failed login attempts for this client after a successful login.
 */
function login_clear_failures(): void
{
    $file = login_throttle_file();

    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Resolve a stored profile picture to a safe, existing upload URL.
 * Returns null when the value is empty or the file is missing on disk.
 */
function avatar_url(?string $picture): ?string
{
    if ($picture === null || $picture === "" || $picture === "0") {
        return null;
    }

    $name = basename($picture);

    if (!is_file(__DIR__ . "/uploads/" . $name)) {
        return null;
    }

    return "uploads/" . $name;
}

/**
 * Delete an uploaded image from disk, if it exists.
 */
function delete_upload(?string $picture): void
{
    if ($picture === null || $picture === "" || $picture === "0") {
        return;
    }

    $path = __DIR__ . "/uploads/" . basename($picture);

    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Securely store an uploaded profile picture.
 *
 * Returns the generated filename, null when no file was uploaded, or null
 * with $error set when the upload was rejected or failed.
 */
function save_profile_picture(string $field, ?string &$error): ?string
{
    $error = null;

    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];

    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $error = "The image upload failed. Please try again.";
        return null;
    }

    $maxBytes = 2 * 1024 * 1024;

    if (($file["size"] ?? 0) > $maxBytes) {
        $error = "The image must be 2 MB or smaller.";
        return null;
    }

    $allowed = [
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "png" => "image/png",
        "gif" => "image/gif",
        "webp" => "image/webp",
    ];

    $ext = strtolower(pathinfo((string) $file["name"], PATHINFO_EXTENSION));

    if (!isset($allowed[$ext])) {
        $error = "Only JPG, JPEG, PNG, GIF, or WEBP images are allowed.";
        return null;
    }

    $info = @getimagesize($file["tmp_name"]);

    if ($info === false || !in_array($info["mime"], array_values($allowed), true)) {
        $error = "The uploaded file is not a valid image.";
        return null;
    }

    $dir = __DIR__ . "/uploads";

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        $error = "The upload directory is unavailable.";
        return null;
    }

    $name = bin2hex(random_bytes(16)) . "." . $ext;

    if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $name)) {
        $error = "The image could not be saved. Please try again.";
        return null;
    }

    return $name;
}

/**
 * Render the shared page header and opening layout.
 */
function page_header(string $title, ?array $user = null): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> &middot; School Management System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="brand">School Management System</div>
        <?php if ($user !== null): ?>
            <div class="userbox">
                <?php $avatar = avatar_url($user["picture"] ?? null); ?>
                <?php if ($avatar !== null): ?>
                    <img class="avatar" src="<?= h($avatar) ?>" alt="Profile picture of <?= h($user["name"]) ?>">
                <?php endif; ?>
                <span class="username"><?= h($user["name"]) ?></span>
                <span class="badge badge-role"><?= h(ucfirst($user["role"])) ?></span>
                <form method="POST" action="logout.php" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-small btn-outline">Logout</button>
                </form>
            </div>
        <?php endif; ?>
    </header>
    <main class="container">
    <?php
}

/**
 * Render the shared page footer and closing layout.
 */
function page_footer(): void
{
    ?>
    </main>
    <footer class="footer">School Management System &middot; Internal use only</footer>
</body>
</html>
    <?php
}
