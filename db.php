<?php

/*
 * Database connection for the School Management System.
 * Credentials live in config.php. Uses mysqli with exceptions enabled so
 * callers can handle failures cleanly. Errors are logged server-side only;
 * no credentials or stack traces are shown to the browser.
 */

require __DIR__ . "/config.php";

if (!defined("DB_HOST") || !defined("DB_USER") || !defined("DB_PASS")
    || !defined("DB_NAME")) {
    error_log("Database configuration is incomplete in config.php");
    http_response_code(500);
    die("Database configuration is incomplete. Please contact the administrator.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        defined("DB_PORT") ? (int) DB_PORT : 3306
    );
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please contact the administrator.");
}
